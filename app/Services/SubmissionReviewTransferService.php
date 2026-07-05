<?php

namespace App\Services;

use App\Enums\ReviewTransferRequestStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\SubmissionReviewTransferRequest;
use App\Models\User;
use App\Notifications\ReviewTransferAcceptedNotification;
use App\Notifications\ReviewTransferRejectedNotification;
use App\Notifications\ReviewTransferRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionReviewTransferService
{
    /**
     * @param  array{to_supervisor_id: int, reason?: string|null}  $data
     */
    public function createRequest(User $supervisor, IpcrSubmission $submission, array $data): SubmissionReviewTransferRequest
    {
        abort_unless($supervisor->isSupervisor(), 403);
        abort_unless($submission->supervisor_id === $supervisor->id, 403);
        $this->assertTransferableSubmission($submission);

        $toSupervisor = User::query()
            ->where('id', $data['to_supervisor_id'])
            ->where('role', UserRole::Supervisor)
            ->firstOrFail();

        if ($toSupervisor->id === $supervisor->id) {
            throw ValidationException::withMessages([
                'to_supervisor_id' => 'Choose a different supervisor for the review transfer.',
            ]);
        }

        $hasPending = SubmissionReviewTransferRequest::query()
            ->where('ipcr_submission_id', $submission->id)
            ->where('status', ReviewTransferRequestStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'to_supervisor_id' => 'This package already has a pending review transfer.',
            ]);
        }

        $transferRequest = SubmissionReviewTransferRequest::create([
            'ipcr_submission_id' => $submission->id,
            'requested_by_id' => $supervisor->id,
            'from_supervisor_id' => $submission->supervisor_id,
            'to_supervisor_id' => $toSupervisor->id,
            'reason' => $data['reason'] ?? null,
            'status' => ReviewTransferRequestStatus::Pending,
        ]);

        $transferRequest->load(['submission.employee', 'requestedBy', 'fromSupervisor', 'toSupervisor']);

        $toSupervisor->notify(new ReviewTransferRequestedNotification($transferRequest));

        AuditLogger::log($supervisor->id, 'submission.review_transfer.requested', $transferRequest, null, request());

        return $transferRequest;
    }

    public function cancelRequest(User $supervisor, SubmissionReviewTransferRequest $transferRequest): void
    {
        abort_unless($transferRequest->requested_by_id === $supervisor->id, 403);
        abort_unless($transferRequest->isPending(), 422);

        $transferRequest->update(['status' => ReviewTransferRequestStatus::Cancelled]);

        AuditLogger::log($supervisor->id, 'submission.review_transfer.cancelled', $transferRequest, null, request());
    }

    public function accept(User $supervisor, SubmissionReviewTransferRequest $transferRequest, ?string $responseNotes = null): void
    {
        abort_unless($supervisor->isSupervisor(), 403);
        abort_unless($transferRequest->to_supervisor_id === $supervisor->id, 403);
        abort_unless($transferRequest->isPending(), 422);

        DB::transaction(function () use ($supervisor, $transferRequest, $responseNotes) {
            $submission = IpcrSubmission::query()
                ->whereKey($transferRequest->ipcr_submission_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertTransferableSubmission($submission);
            abort_unless($submission->supervisor_id === $transferRequest->from_supervisor_id, 422);

            $submission->update([
                'supervisor_id' => $transferRequest->to_supervisor_id,
            ]);

            $transferRequest->update([
                'status' => ReviewTransferRequestStatus::Accepted,
                'responded_at' => now(),
                'response_notes' => $responseNotes,
            ]);
        });

        $transferRequest->load(['submission.employee', 'requestedBy', 'fromSupervisor', 'toSupervisor']);

        $transferRequest->requestedBy?->notify(new ReviewTransferAcceptedNotification($transferRequest));

        AuditLogger::log($supervisor->id, 'submission.review_transfer.accepted', $transferRequest, null, request());
    }

    public function reject(User $supervisor, SubmissionReviewTransferRequest $transferRequest, ?string $responseNotes = null): void
    {
        abort_unless($supervisor->isSupervisor(), 403);
        abort_unless($transferRequest->to_supervisor_id === $supervisor->id, 403);
        abort_unless($transferRequest->isPending(), 422);

        $transferRequest->update([
            'status' => ReviewTransferRequestStatus::Rejected,
            'responded_at' => now(),
            'response_notes' => $responseNotes,
        ]);

        $transferRequest->load(['submission.employee', 'requestedBy', 'fromSupervisor', 'toSupervisor']);

        $transferRequest->requestedBy?->notify(new ReviewTransferRejectedNotification($transferRequest));

        AuditLogger::log($supervisor->id, 'submission.review_transfer.rejected', $transferRequest, null, request());
    }

    private function assertTransferableSubmission(IpcrSubmission $submission): void
    {
        if (! in_array($submission->status, [SubmissionStatus::InReview, SubmissionStatus::Returned], true)) {
            throw ValidationException::withMessages([
                'submission' => 'Only packages in review or returned status can be transferred.',
            ]);
        }
    }
}
