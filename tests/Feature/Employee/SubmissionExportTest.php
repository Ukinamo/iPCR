<?php

namespace Tests\Feature\Employee;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_export_own_approved_submission(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::Approved,
            'overall_rating' => 4.5,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get(
            route('employee.submissions.export', ['submission' => $submission, 'format' => 'xlsx']),
        );

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    }

    public function test_employee_cannot_export_another_employees_submission(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $submission = IpcrSubmission::create([
            'employee_id' => $other->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::Approved,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get(
            route('employee.submissions.export', ['submission' => $submission, 'format' => 'xlsx']),
        );

        $response->assertForbidden();
    }

    public function test_employee_cannot_export_unapproved_submission(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2026,
            'evaluation_quarter' => 2,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get(
            route('employee.submissions.export', ['submission' => $submission, 'format' => 'pdf']),
        );

        $response->assertStatus(422);
    }
}
