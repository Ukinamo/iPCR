<?php

namespace Tests\Feature\Admin;

use App\Enums\SubmissionStatus;
use App\Enums\TransferRequestStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\SupervisorTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_request_employee_transfer(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);

        $response = $this->actingAs($supervisorA)->post(route('supervisor.transfer-requests.store'), [
            'employee_id' => $employee->id,
            'to_supervisor_id' => $supervisorB->id,
            'reason' => 'Organizational realignment.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('supervisor_transfer_requests', [
            'employee_id' => $employee->id,
            'requested_by_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => TransferRequestStatus::Pending->value,
        ]);
    }

    public function test_admin_approval_reassigns_employee_and_open_submissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
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

        $request = SupervisorTransferRequest::create([
            'employee_id' => $employee->id,
            'requested_by_id' => $supervisorA->id,
            'from_supervisor_id' => $supervisorA->id,
            'to_supervisor_id' => $supervisorB->id,
            'status' => TransferRequestStatus::Pending,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.transfer-requests.approve', $request));

        $response->assertRedirect();
        $employee->refresh();
        $submission->refresh();
        $request->refresh();

        $this->assertSame($supervisorB->id, $employee->supervisor_id);
        $this->assertSame($supervisorB->id, $submission->supervisor_id);
        $this->assertSame(TransferRequestStatus::Approved, $request->status);
    }

    public function test_new_supervisor_can_review_reassigned_submission(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorB->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorB->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($supervisorB)->get(route('supervisor.submissions.show', $submission));

        $response->assertOk();
    }

    public function test_old_supervisor_cannot_review_after_reassignment(): void
    {
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorB->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisorB->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($supervisorA)->get(route('supervisor.submissions.show', $submission));

        $response->assertForbidden();
    }
}
