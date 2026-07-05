<?php

namespace App\Notifications;

use App\Models\IpcrSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IpcrSubmittedForReviewNotification extends Notification
{
    use Queueable;

    public function __construct(public IpcrSubmission $submission) {}

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
        $employee = $this->submission->employee;

        return [
            'type' => 'ipcr_submitted',
            'title' => 'IPCR package submitted',
            'message' => sprintf(
                '%s submitted Q%d %d for your review.',
                $employee?->name ?? 'An employee',
                $this->submission->evaluation_quarter,
                $this->submission->evaluation_year,
            ),
            'url' => route('supervisor.submissions.show', $this->submission->id),
            'submission_id' => $this->submission->id,
        ];
    }
}
