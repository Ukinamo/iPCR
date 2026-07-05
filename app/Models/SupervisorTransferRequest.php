<?php

namespace App\Models;

use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorTransferRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'requested_by_id',
        'from_supervisor_id',
        'to_supervisor_id',
        'reason',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function fromSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_supervisor_id');
    }

    public function toSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_supervisor_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === TransferRequestStatus::Pending;
    }
}
