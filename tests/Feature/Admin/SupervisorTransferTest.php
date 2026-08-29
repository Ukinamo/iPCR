<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reassign_employee_and_open_submissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'account_status' => AccountStatus::Active,
            'supervisor_id' => $supervisorA->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorA->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $employee), [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => UserRole::Employee->value,
            'account_status' => AccountStatus::Active->value,
            'supervisor_id' => $supervisorB->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $employee->refresh();
        $submission->refresh();

        $this->assertSame($supervisorB->id, $employee->supervisor_id);
        $this->assertSame($supervisorB->id, $submission->supervisor_id);
    }

    public function test_supervisor_cannot_review_submissions(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.submissions.show', $submission))
            ->assertForbidden();
    }
}
