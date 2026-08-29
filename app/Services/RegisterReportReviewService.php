<?php

namespace App\Services;

use App\Enums\RegisterReportStatus;
use App\Enums\UserRole;
use App\Models\ProgramEvaluationForm;
use App\Models\StoMonitoringForm;
use App\Models\User;
use App\Notifications\RegisterReportReviewedNotification;
use App\Notifications\RegisterReportSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegisterReportReviewService
{
    public function submit(ProgramEvaluationForm|StoMonitoringForm $form, User $actor, Request $request): void
    {
        abort_unless($form->supervisor_id === $actor->id, 403);
        abort_unless($form->supervisorCanEdit(), 422, 'This report cannot be submitted in its current status.');

        if ($form->entries()->count() < 1) {
            throw ValidationException::withMessages([
                'entries' => 'Add at least one row before submitting this report for administrator review.',
            ]);
        }

        $form->update([
            'status' => RegisterReportStatus::InReview,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_id' => null,
            'review_notes' => null,
        ]);

        AuditLogger::log($actor->id, $this->eventName($form, 'submitted'), $form, null, $request);

        $label = $this->label($form);
        $url = $form instanceof ProgramEvaluationForm
            ? route('admin.program-evaluations.show', $form)
            : route('admin.sto-monitoring.show', $form);

        $this->notifyAdministrators(new RegisterReportSubmittedNotification(
            'Supervisor report submitted',
            sprintf('%s submitted “%s” for administrator review.', $actor->name, $label),
            $url,
        ));
    }

    public function review(
        ProgramEvaluationForm|StoMonitoringForm $form,
        User $admin,
        string $action,
        ?string $notes,
        Request $request,
    ): void {
        abort_unless($admin->isAdministrator(), 403);
        abort_unless($form->status === RegisterReportStatus::InReview, 422, 'This report is not waiting for review.');

        $approved = $action === 'approve';
        $notesText = trim((string) $notes);

        if (! $approved && strlen($notesText) < 10) {
            throw ValidationException::withMessages([
                'review_notes' => 'Returning a report requires at least 10 characters of guidance for the supervisor.',
            ]);
        }

        $form->update([
            'status' => $approved ? RegisterReportStatus::Approved : RegisterReportStatus::Returned,
            'reviewed_at' => now(),
            'reviewer_id' => $admin->id,
            'review_notes' => $notesText !== '' ? $notesText : null,
        ]);

        AuditLogger::log($admin->id, $this->eventName($form, $approved ? 'approved' : 'returned'), $form, [
            'notes' => $form->review_notes,
        ], $request);

        $label = $this->label($form);
        $form->loadMissing('supervisor');
        $form->supervisor?->notify(new RegisterReportReviewedNotification(
            $approved ? 'Supervisor report approved' : 'Supervisor report returned',
            sprintf(
                'Your report “%s” was %s by an administrator.',
                $label,
                $approved ? 'approved' : 'returned for revision',
            ),
            $approved,
        ));
    }

    private function label(ProgramEvaluationForm|StoMonitoringForm $form): string
    {
        return $form->title ?: 'Supervisor report';
    }

    private function eventName(ProgramEvaluationForm|StoMonitoringForm $form, string $action): string
    {
        $prefix = $form instanceof ProgramEvaluationForm ? 'program_evaluation' : 'sto_monitoring';

        return $prefix.'.'.$action;
    }

    private function notifyAdministrators(RegisterReportSubmittedNotification $notification): void
    {
        User::query()
            ->where('role', UserRole::Administrator)
            ->where('account_status', 'active')
            ->each(fn (User $admin) => $admin->notify($notification));
    }
}
