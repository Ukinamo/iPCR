<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PasswordResetOtpNotification extends Notification
{
    public function __construct(
        public readonly string $otp,
        public readonly int $expireMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your I-PERFORM password reset code')
            ->greeting('Hello!')
            ->line('You requested a password reset for your I-PERFORM account.')
            ->line('Use this one-time verification code:')
            ->line(new HtmlString(
                '<p style="font-size: 32px; font-weight: 700; letter-spacing: 0.35em; margin: 16px 0;">'
                .e($this->otp)
                .'</p>'
            ))
            ->line('This code expires in '.$this->expireMinutes.' minutes.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
