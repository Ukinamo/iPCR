<?php

namespace App\Notifications;

use App\Models\SubmissionReviewTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewTransferRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public SubmissionReviewTransferRequest $transferRequest) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $submission = $this->transferRequest->submission;
        $employee = $submission?->employee;
        $toSupervisor = $this->transferRequest->toSupervisor;

        return [
            'type' => 'review_transfer_rejected',
            'title' => 'Review transfer declined',
            'message' => sprintf(
                '%s declined to take over review of %s\'s Q%d %d package.',
                $toSupervisor?->name ?? 'The supervisor',
                $employee?->name ?? 'the employee',
                $submission?->evaluation_quarter ?? 0,
                $submission?->evaluation_year ?? 0,
            ),
            'url' => route('supervisor.submissions.show', $this->transferRequest->ipcr_submission_id),
            'review_transfer_request_id' => $this->transferRequest->id,
            'submission_id' => $this->transferRequest->ipcr_submission_id,
        ];
    }
}
