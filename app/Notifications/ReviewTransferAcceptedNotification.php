<?php

namespace App\Notifications;

use App\Models\SubmissionReviewTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewTransferAcceptedNotification extends Notification
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
            'type' => 'review_transfer_accepted',
            'title' => 'Review transfer accepted',
            'message' => sprintf(
                '%s accepted review of %s\'s Q%d %d package. The employee remains on your team.',
                $toSupervisor?->name ?? 'The supervisor',
                $employee?->name ?? 'the employee',
                $submission?->evaluation_quarter ?? 0,
                $submission?->evaluation_year ?? 0,
            ),
            'url' => route('dashboard'),
            'review_transfer_request_id' => $this->transferRequest->id,
            'submission_id' => $this->transferRequest->ipcr_submission_id,
        ];
    }
}
