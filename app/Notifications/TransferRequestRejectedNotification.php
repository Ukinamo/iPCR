<?php

namespace App\Notifications;

use App\Models\SupervisorTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferRequestRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public SupervisorTransferRequest $transferRequest) {}

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
        $employee = $this->transferRequest->employee;

        return [
            'type' => 'transfer_request_rejected',
            'title' => 'Supervisor transfer rejected',
            'message' => sprintf(
                'Your transfer request for %s was rejected by an administrator.',
                $employee?->name ?? 'the employee',
            ),
            'url' => route('dashboard'),
            'transfer_request_id' => $this->transferRequest->id,
        ];
    }
}
