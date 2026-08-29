<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegisterReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url,
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
            'type' => 'register_report_submitted',
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
