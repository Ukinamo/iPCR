<?php

namespace App\Models;

use App\Enums\ReviewTransferRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionReviewTransferRequest extends Model
{
    protected $fillable = [
        'ipcr_submission_id',
        'requested_by_id',
        'from_supervisor_id',
        'to_supervisor_id',
        'reason',
        'status',
        'responded_at',
        'response_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewTransferRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(IpcrSubmission::class, 'ipcr_submission_id');
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

    public function isPending(): bool
    {
        return $this->status === ReviewTransferRequestStatus::Pending;
    }
}
