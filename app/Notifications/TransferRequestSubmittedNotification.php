<?php

namespace App\Notifications;

use App\Models\SupervisorTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferRequestSubmittedNotification extends Notification
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
        $requester = $this->transferRequest->requestedBy;

        return [
            'type' => 'transfer_request_submitted',
            'title' => 'Supervisor transfer pending approval',
            'message' => sprintf(
                '%s requested to transfer %s to %s.',
                $requester?->name ?? 'A supervisor',
                $employee?->name ?? 'an employee',
                $toSupervisor?->name ?? 'another supervisor',
            ),
            'url' => route('admin.transfer-requests.index'),
            'transfer_request_id' => $this->transferRequest->id,
        ];
    }
}
