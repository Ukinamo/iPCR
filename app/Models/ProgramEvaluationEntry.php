<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEvaluationEntry extends Model
{
    protected $fillable = [
        'program_evaluation_form_id',
        'sort_order',
        'institutional_code',
        'hei_name',
        'institutional_type',
        'program_name',
        'program_count',
        'purpose',
        'effectivity_ay',
        'date_applied',
        'date_evaluated',
        'result',
        'final_recommendation',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'program_count' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(ProgramEvaluationForm::class, 'program_evaluation_form_id');
    }
}
