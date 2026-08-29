<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoMonitoringEntry extends Model
{
    protected $fillable = [
        'sto_monitoring_form_id',
        'sort_order',
        'hei_name',
        'monitored_item',
        'grantee_count',
        'date_monitored',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'grantee_count' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(StoMonitoringForm::class, 'sto_monitoring_form_id');
    }
}
