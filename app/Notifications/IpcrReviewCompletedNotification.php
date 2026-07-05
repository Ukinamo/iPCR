<?php

namespace App\Notifications;

use App\Models\IpcrSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IpcrReviewCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public IpcrSubmission $submission,
        public string $action,
    ) {}

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
        $approved = $this->action === 'approve';

        return [
            'type' => $approved ? 'ipcr_approved' : 'ipcr_returned',
            'title' => $approved ? 'IPCR package approved' : 'IPCR package returned',
            'message' => sprintf(
                'Your Q%d %d IPCR package was %s by your supervisor.',
                $this->submission->evaluation_quarter,
                $this->submission->evaluation_year,
                $approved ? 'approved' : 'returned for revision',
            ),
            'url' => route('dashboard'),
            'submission_id' => $this->submission->id,
        ];
    }
}
