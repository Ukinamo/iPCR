<?php

namespace Tests\Feature\Employee;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitmentPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_add_commitments_while_submission_is_in_review(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        IpcrSubmission::query()->create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        Commitment::query()->create([
            'user_id' => $employee->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => 'Q'.$quarter.' '.$year,
            'title' => 'Existing function',
            'description' => 'Indicator',
            'function_type' => 'core',
            'weight' => 60,
            'progress' => 0,
            'status' => CommitmentStatus::InReview,
        ]);

        $response = $this->actingAs($employee)->post(route('employee.commitments.store'), [
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => 'Q'.$quarter.' '.$year,
            'entries' => [[
                'function_type' => 'core',
                'title' => 'New function',
                'description' => 'Indicator',
                'weight' => 10,
            ]],
        ]);

        $response->assertSessionHasErrors('entries');
    }
}
