<?php

namespace App\Models;

use App\Enums\RegisterReportStatus;
use App\Models\Concerns\HasRegisterReportReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramEvaluationForm extends Model
{
    use HasRegisterReportReview;

    protected $fillable = [
        'supervisor_id',
        'title',
        'office_name',
        'evaluation_year',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewer_id',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_year' => 'integer',
            'status' => RegisterReportStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProgramEvaluationEntry::class)->orderBy('sort_order')->orderBy('id');
    }
}
