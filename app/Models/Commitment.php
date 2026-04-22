<?php

namespace App\Models;

use App\Enums\CommitmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commitment extends Model
{
    protected $fillable = [
        'user_id',
        'ipcr_submission_id',
        'evaluation_year',
        'evaluation_quarter',
        'period_label',
        'title',
        'description',
        'function_type',
        'weight',
        'progress',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommitmentStatus::class,
            'weight' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(IpcrSubmission::class, 'ipcr_submission_id');
    }
}
