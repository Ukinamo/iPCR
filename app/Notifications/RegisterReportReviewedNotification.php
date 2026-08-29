<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegisterReportReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public bool $approved,
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
        return [
            'type' => $this->approved ? 'register_report_approved' : 'register_report_returned',
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('dashboard', ['tab' => 'programs']),
        ];
    }
}
