<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_registration_with_supervisor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $pending = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Pending,
            'supervisor_id' => null,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.approve', $pending), [
            'supervisor_id' => $supervisor->id,
        ]);

        $response->assertRedirect(route('admin.users.pending'));
        $pending->refresh();

        $this->assertSame(AccountStatus::Active, $pending->account_status);
        $this->assertSame($supervisor->id, $pending->supervisor_id);
    }

    public function test_admin_can_reject_pending_registration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $pending = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.users.reject', $pending));

        $response->assertRedirect(route('admin.users.pending'));
        $this->assertDatabaseMissing('users', ['id' => $pending->id]);
    }
}
