<?php

namespace App\Notifications;

use App\Models\IpcrFormTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IpcrFormAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public IpcrFormTemplate $template,
        public string $audience = 'employee',
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
        $period = 'Q'.$this->template->evaluation_quarter.' '.$this->template->evaluation_year;
        $forEmployee = $this->audience === 'employee';

        return [
            'type' => 'ipcr_form_assigned',
            'title' => $forEmployee ? 'IPCR form is ready' : 'IPCR form assigned to your team',
            'message' => $forEmployee
                ? sprintf('Your %s IPCR form has been assigned. Fill in accomplishments, then submit for review.', $period)
                : sprintf('An administrator assigned the %s IPCR form for your employees to complete.', $period),
            'url' => route('dashboard'),
            'template_id' => $this->template->id,
        ];
    }
}
