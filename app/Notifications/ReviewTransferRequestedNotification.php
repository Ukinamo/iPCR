<?php

namespace App\Notifications;

use App\Models\SubmissionReviewTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewTransferRequestedNotification extends Notification
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
        $requester = $this->transferRequest->requestedBy;

        return [
            'type' => 'review_transfer_requested',
            'title' => 'Review transfer request',
            'message' => sprintf(
                '%s asked you to take over review of %s\'s Q%d %d IPCR package.',
                $requester?->name ?? 'A supervisor',
                $employee?->name ?? 'an employee',
                $submission?->evaluation_quarter ?? 0,
                $submission?->evaluation_year ?? 0,
            ),
            'url' => route('dashboard'),
            'review_transfer_request_id' => $this->transferRequest->id,
            'submission_id' => $this->transferRequest->ipcr_submission_id,
        ];
    }
}
