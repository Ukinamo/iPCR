<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'account_status', 'supervisor_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
}
