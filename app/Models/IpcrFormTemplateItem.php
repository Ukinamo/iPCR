<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrFormTemplateItem extends Model
{
    protected $fillable = [
        'ipcr_form_template_id',
        'sort_order',
        'function_group',
        'function_type',
        'title',
        'description',
        'weight',
        'annual_office_target',
        'individual_annual_targets',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'function_group' => 'integer',
            'weight' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IpcrFormTemplate::class, 'ipcr_form_template_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }
}
