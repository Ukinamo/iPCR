<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Active,
            'supervisor_id' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Administrator,
            'supervisor_id' => null,
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Supervisor,
            'supervisor_id' => null,
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Employee,
        ]);
    }
}
