<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\TransferRequestStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\SupervisorTransferRequest;
use App\Models\User;
use App\Notifications\IpcrReviewCompletedNotification;
use App\Notifications\IpcrSubmittedForReviewNotification;
use App\Notifications\TransferRequestApprovedNotification;
use App\Notifications\TransferRequestRejectedNotification;
use App\Notifications\TransferRequestSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupervisorTransferService
{
    public function createRequest(User $supervisor, array $data): SupervisorTransferRequest
    {
        abort_unless($supervisor->isSupervisor(), 403);

        $employee = User::query()
            ->where('id', $data['employee_id'])
            ->where('role', UserRole::Employee)
            ->where('supervisor_id', $supervisor->id)
            ->firstOrFail();

        $toSupervisor = User::query()
            ->where('id', $data['to_supervisor_id'])
            ->where('role', UserRole::Supervisor)
            ->firstOrFail();

        if ($toSupervisor->id === $supervisor->id) {
            throw ValidationException::withMessages([
                'to_supervisor_id' => 'Choose a different supervisor for the transfer.',
            ]);
        }

        $hasPending = SupervisorTransferRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', TransferRequestStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee already has a pending transfer request.',
            ]);
        }

        $request = SupervisorTransferRequest::create([
            'employee_id' => $employee->id,
            'requested_by_id' => $supervisor->id,
            'from_supervisor_id' => $employee->supervisor_id,
            'to_supervisor_id' => $toSupervisor->id,
            'reason' => $data['reason'] ?? null,
            'status' => TransferRequestStatus::Pending,
        ]);

        $request->load(['employee', 'requestedBy', 'fromSupervisor', 'toSupervisor']);

        $this->notifyAdministrators(new TransferRequestSubmittedNotification($request));

        AuditLogger::log($supervisor->id, 'supervisor.transfer.requested', $request, null, request());

        return $request;
    }

    public function cancelRequest(User $supervisor, SupervisorTransferRequest $transferRequest): void
    {
        abort_unless($transferRequest->requested_by_id === $supervisor->id, 403);
        abort_unless($transferRequest->isPending(), 422);

        $transferRequest->update(['status' => TransferRequestStatus::Cancelled]);

        AuditLogger::log($supervisor->id, 'supervisor.transfer.cancelled', $transferRequest, null, request());
    }

    public function approve(User $admin, SupervisorTransferRequest $transferRequest, ?string $adminNotes = null): void
    {
        abort_unless($admin->isAdministrator(), 403);
        abort_unless($transferRequest->isPending(), 422);

        DB::transaction(function () use ($admin, $transferRequest, $adminNotes) {
            $employee = $transferRequest->employee()->lockForUpdate()->firstOrFail();

            $employee->update([
                'supervisor_id' => $transferRequest->to_supervisor_id,
            ]);

            IpcrSubmission::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [
                    SubmissionStatus::Pending,
                    SubmissionStatus::InReview,
                    SubmissionStatus::Returned,
                ])
                ->update([
                    'supervisor_id' => $transferRequest->to_supervisor_id,
                ]);

            $transferRequest->update([
                'status' => TransferRequestStatus::Approved,
                'reviewed_by_id' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);
        });

        app(IpcrFormTemplateProvisioner::class)->provisionAssignedForEmployee(
            $transferRequest->employee()->firstOrFail()
        );

        $transferRequest->load(['employee', 'requestedBy', 'fromSupervisor', 'toSupervisor', 'reviewedBy']);

        $this->notifyTransferParties(new TransferRequestApprovedNotification($transferRequest));

        AuditLogger::log($admin->id, 'supervisor.transfer.approved', $transferRequest, null, request());
    }

    public function reject(User $admin, SupervisorTransferRequest $transferRequest, ?string $adminNotes = null): void
    {
        abort_unless($admin->isAdministrator(), 403);
        abort_unless($transferRequest->isPending(), 422);

        $transferRequest->update([
            'status' => TransferRequestStatus::Rejected,
            'reviewed_by_id' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $adminNotes,
        ]);

        $transferRequest->load(['employee', 'requestedBy', 'fromSupervisor', 'toSupervisor', 'reviewedBy']);

        $transferRequest->requestedBy?->notify(new TransferRequestRejectedNotification($transferRequest));

        AuditLogger::log($admin->id, 'supervisor.transfer.rejected', $transferRequest, null, request());
    }

    public function notifySubmissionSubmitted(IpcrSubmission $submission): void
    {
        $submission->loadMissing(['employee', 'supervisor']);

        $submission->supervisor?->notify(new IpcrSubmittedForReviewNotification($submission));
    }

    public function notifyReviewCompleted(IpcrSubmission $submission, string $action): void
    {
        $submission->loadMissing(['employee', 'supervisor']);

        $submission->employee?->notify(new IpcrReviewCompletedNotification($submission, $action));
    }

    /**
     * @param  \Illuminate\Notifications\Notification  $notification
     */
    private function notifyAdministrators($notification): void
    {
        User::query()
            ->where('role', UserRole::Administrator)
            ->where('account_status', 'active')
            ->each(fn (User $admin) => $admin->notify($notification));
    }

    /**
     * @param  TransferRequestApprovedNotification  $notification
     */
    private function notifyTransferParties($notification): void
    {
        $transferRequest = $notification->transferRequest;

        $recipients = collect([
            $transferRequest->employee,
            $transferRequest->requestedBy,
            $transferRequest->fromSupervisor,
            $transferRequest->toSupervisor,
        ])->filter()->unique('id');

        $recipients->each(fn (User $user) => $user->notify($notification));
    }
}
