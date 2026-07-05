<?php

namespace App\Notifications;

use App\Models\SupervisorTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferRequestApprovedNotification extends Notification
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
        $toSupervisor = $this->transferRequest->toSupervisor;

        return [
            'type' => 'transfer_request_approved',
            'title' => 'Supervisor transfer approved',
            'message' => sprintf(
                '%s is now assigned to %s. Future IPCR submissions will route to the new supervisor.',
                $employee?->name ?? 'The employee',
                $toSupervisor?->name ?? 'the new supervisor',
            ),
            'url' => route('dashboard'),
            'transfer_request_id' => $this->transferRequest->id,
        ];
    }
}
