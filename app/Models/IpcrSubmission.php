<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrSubmission extends Model
{
    protected $fillable = [
        'employee_id',
        'supervisor_id',
        'evaluation_year',
        'evaluation_quarter',
        'status',
        'quality',
        'efficiency',
        'timeliness',
        'overall_rating',
        'supervisor_feedback',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'overall_rating' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class, 'ipcr_submission_id');
    }
}
