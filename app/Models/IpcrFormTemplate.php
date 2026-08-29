<?php

namespace App\Models;

use App\Enums\FormTemplateStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrFormTemplate extends Model
{
    protected $fillable = [
        'created_by',
        'evaluation_year',
        'evaluation_quarter',
        'period_label',
        'title',
        'status',
        'assigned_at',
        'included_quarters',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormTemplateStatus::class,
            'assigned_at' => 'datetime',
            'included_quarters' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'ipcr_form_template_supervisors',
            'ipcr_form_template_id',
            'supervisor_id',
        )->withTimestamps()->withPivot('assigned_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IpcrFormTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    public function isAssigned(): bool
    {
        return $this->status === FormTemplateStatus::Assigned;
    }

    public function assignedEmployeeCount(): int
    {
        $supervisorIds = $this->relationLoaded('supervisors')
            ? $this->supervisors->pluck('id')
            : $this->supervisors()->pluck('users.id');

        if ($supervisorIds->isEmpty()) {
            return 0;
        }

        return User::query()
            ->where('role', UserRole::Employee)
            ->whereIn('supervisor_id', $supervisorIds)
            ->count();
    }
}
