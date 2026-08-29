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
        'included_quarters',
        'status',
        'quality',
        'efficiency',
        'timeliness',
        'overall_rating',
        'supervisor_feedback',
        'commitment_statement',
        'submitted_at',
        'reviewed_at',
        'batch_id',
        'title',
        'ipcr_form_template_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'overall_rating' => 'decimal:2',
            'included_quarters' => 'array',
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

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(IpcrFormTemplate::class, 'ipcr_form_template_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class, 'ipcr_submission_id')->inFormOrder();
    }
}
