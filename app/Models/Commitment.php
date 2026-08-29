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
        'batch_id',
        'ipcr_form_template_id',
        'ipcr_form_template_item_id',
        'ipcr_submission_id',
        'evaluation_year',
        'evaluation_quarter',
        'period_label',
        'title',
        'description',
        'function_type',
        'sort_order',
        'function_group',
        'weight',
        'annual_office_target',
        'individual_annual_targets',
        'progress',
        'rating_actual_total',
        'rating_target_total',
        'rating_q1_target',
        'rating_q1_actual',
        'rating_q2_target',
        'rating_q2_actual',
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
            'sort_order' => 'integer',
            'function_group' => 'integer',
            'weight' => 'decimal:2',
            'rating_actual_total' => 'integer',
            'rating_target_total' => 'integer',
            'rating_q1_target' => 'integer',
            'rating_q1_actual' => 'integer',
            'rating_q2_target' => 'integer',
            'rating_q2_actual' => 'integer',
            'rating_q3_target' => 'integer',
            'rating_q3_actual' => 'integer',
            'rating_q4_target' => 'integer',
            'rating_q4_actual' => 'integer',
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

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(IpcrFormTemplate::class, 'ipcr_form_template_id');
    }

    public function formTemplateItem(): BelongsTo
    {
        return $this->belongsTo(IpcrFormTemplateItem::class, 'ipcr_form_template_item_id');
    }

    /**
     * Evidence of work (files, photos, documents) supporting this commitment.
     */
    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class)->orderByDesc('id');
    }

    public function scopeInFormOrder($query)
    {
        return $query
            ->orderByRaw("CASE WHEN function_type = 'core' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
