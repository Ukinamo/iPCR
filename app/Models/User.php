<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'role', 'account_status', 'supervisor_id', 'profile_photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_status' => AccountStatus::class,
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function supervisees(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class);
    }

    public function ipcrSubmissionsAsEmployee(): HasMany
    {
        return $this->hasMany(IpcrSubmission::class, 'employee_id');
    }

    public function ipcrSubmissionsAsSupervisor(): HasMany
    {
        return $this->hasMany(IpcrSubmission::class, 'supervisor_id');
    }

    public function assignedIpcrFormTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            IpcrFormTemplate::class,
            'ipcr_form_template_supervisors',
            'supervisor_id',
            'ipcr_form_template_id',
        )->withTimestamps()->withPivot('assigned_at');
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    public function isSupervisor(): bool
    {
        return $this->role === UserRole::Supervisor;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isActive(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->account_status === AccountStatus::Pending;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo_path === null) {
            return null;
        }

        if (! app()->runningInConsole() && Route::has('users.profile-photo')) {
            $version = $this->updated_at?->timestamp ?? time();

            return route('users.profile-photo', $this->id).'?v='.$version;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }
}
