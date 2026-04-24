<?php

namespace App\Models;

use App\Enums\CommitmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'annual_office_target',
        'individual_annual_targets',
        'progress',
        'rating_actual_total',
        'rating_target_total',
        'rating_q3_target',
        'rating_q3_actual',
        'rating_q4_target',
        'rating_q4_actual',
        'rating_percent',
        'rating_quality',
        'rating_efficiency',
        'rating_timeliness',
        'rating_average',
        'rating_weighted',
        'remarks',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommitmentStatus::class,
            'weight' => 'decimal:2',
            'rating_actual_total' => 'decimal:4',
            'rating_target_total' => 'decimal:4',
            'rating_q3_target' => 'decimal:4',
            'rating_q3_actual' => 'decimal:4',
            'rating_q4_target' => 'decimal:4',
            'rating_q4_actual' => 'decimal:4',
            'rating_percent' => 'decimal:6',
            'rating_average' => 'decimal:4',
            'rating_weighted' => 'decimal:6',
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

    /**
     * Evidence of work (files, photos, documents) supporting this commitment.
     */
    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class)->orderByDesc('id');
    }
}
