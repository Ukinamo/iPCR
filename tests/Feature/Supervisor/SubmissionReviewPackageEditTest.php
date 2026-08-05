<?php

namespace Tests\Feature\Supervisor;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionReviewPackageEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_edit_package_fields_add_and_remove_rows_on_return(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $keep = Commitment::query()->create([
            'user_id' => $employee->id,
            'ipcr_submission_id' => $submission->id,
            'batch_id' => 'batch-1',
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'period_label' => 'Q3 2025',
            'function_type' => 'core',
            'title' => 'Original Function',
            'description' => 'Old indicator',
            'weight' => 30,
            'annual_office_target' => '10',
            'individual_annual_targets' => '5',
            'progress' => 0,
            'status' => CommitmentStatus::InReview,
        ]);

        $remove = Commitment::query()->create([
            'user_id' => $employee->id,
            'ipcr_submission_id' => $submission->id,
            'batch_id' => 'batch-1',
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'period_label' => 'Q3 2025',
            'function_type' => 'core',
            'title' => 'Original Function',
            'description' => 'Remove me',
            'weight' => 10,
            'annual_office_target' => '2',
            'individual_annual_targets' => '1',
            'progress' => 0,
            'status' => CommitmentStatus::InReview,
        ]);

        $response = $this->actingAs($supervisor)->patch(route('supervisor.submissions.update', $submission), [
            'action' => 'return',
            'supervisor_feedback' => 'Please revise the indicators and weights before resubmitting.',
            'commitments' => [
                [
                    'id' => $keep->id,
                    'function_type' => 'core',
                    'title' => 'Updated Function',
                    'description' => "Indicator A\nIndicator B",
                    'weight' => 40,
                    'annual_office_target' => '12',
                    'individual_annual_targets' => '6',
                ],
                [
                    'id' => null,
                    'function_type' => 'strategic',
                    'title' => 'New Strategic Function',
                    'description' => 'New strategic indicator',
                    'weight' => 20,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Returned, $submission->status);

        $this->assertDatabaseMissing('commitments', ['id' => $remove->id]);

        $keep->refresh();
        $this->assertSame('Updated Function', $keep->title);
        $this->assertSame("Indicator A\nIndicator B", $keep->description);
        $this->assertEquals(40.0, (float) $keep->weight);
        $this->assertSame('12', $keep->annual_office_target);
        $this->assertSame('6', $keep->individual_annual_targets);
        $this->assertSame(CommitmentStatus::Returned, $keep->status);

        $this->assertDatabaseHas('commitments', [
            'ipcr_submission_id' => $submission->id,
            'function_type' => 'strategic',
            'title' => 'New Strategic Function',
            'description' => 'New strategic indicator',
            'annual_office_target' => '4',
            'individual_annual_targets' => '2',
            'status' => CommitmentStatus::Returned->value,
        ]);

        $this->assertSame(2, $submission->commitments()->count());
    }

    public function test_supervisor_can_approve_with_edited_weights_and_new_row(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 4,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $commitment = Commitment::query()->create([
            'user_id' => $employee->id,
            'ipcr_submission_id' => $submission->id,
            'batch_id' => 'batch-2',
            'evaluation_year' => 2025,
            'evaluation_quarter' => 4,
            'period_label' => 'Q4 2025',
            'function_type' => 'core',
            'title' => 'Core Work',
            'description' => 'Deliverable',
            'weight' => 50,
            'annual_office_target' => '10',
            'individual_annual_targets' => '5',
            'progress' => 0,
            'status' => CommitmentStatus::InReview,
        ]);

        $response = $this->actingAs($supervisor)->patch(route('supervisor.submissions.update', $submission), [
            'action' => 'approve',
            'supervisor_feedback' => 'Good work.',
            'commitments' => [
                [
                    'id' => $commitment->id,
                    'function_type' => 'core',
                    'title' => 'Core Work',
                    'description' => 'Deliverable revised',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                    'rating_quality' => 4,
                    'rating_efficiency' => 4,
                    'rating_timeliness' => 4,
                    'rating_q3_target' => 2,
                    'rating_q3_actual' => 2,
                    'rating_q4_target' => 3,
                    'rating_q4_actual' => 3,
                ],
                [
                    'id' => null,
                    'function_type' => 'strategic',
                    'title' => 'Strategic Work',
                    'description' => 'Strategic indicator',
                    'weight' => 40,
                    'annual_office_target' => '8',
                    'individual_annual_targets' => '4',
                    'rating_quality' => 3,
                    'rating_efficiency' => 3,
                    'rating_timeliness' => 3,
                    'rating_q3_target' => 1,
                    'rating_q3_actual' => 1,
                    'rating_q4_target' => 1,
                    'rating_q4_actual' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Approved, $submission->status);

        $commitment->refresh();
        $this->assertSame('Deliverable revised', $commitment->description);
        $this->assertEquals(60.0, (float) $commitment->weight);
        $this->assertSame(CommitmentStatus::Approved, $commitment->status);
        $this->assertEquals(4.0, (float) $commitment->rating_average);
        $this->assertEquals(2.4, (float) $commitment->rating_weighted);

        $strategic = $submission->commitments()->where('function_type', 'strategic')->first();
        $this->assertNotNull($strategic);
        $this->assertSame('Strategic Work', $strategic->title);
        $this->assertEquals(40.0, (float) $strategic->weight);
        $this->assertEquals(3.0, (float) $strategic->rating_average);
        $this->assertEquals(1.2, (float) $strategic->rating_weighted);

        $this->assertEquals(3.6, (float) $submission->overall_rating);
    }

    public function test_supervisor_can_save_package_edits_without_leaving_review(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now(),
        ]);

        $commitment = Commitment::query()->create([
            'user_id' => $employee->id,
            'ipcr_submission_id' => $submission->id,
            'batch_id' => 'batch-save',
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'period_label' => 'Q3 2025',
            'function_type' => 'core',
            'title' => 'Original Function',
            'description' => 'Original indicator',
            'weight' => 30,
            'annual_office_target' => '10',
            'individual_annual_targets' => '5',
            'progress' => 0,
            'status' => CommitmentStatus::InReview,
        ]);

        $response = $this->actingAs($supervisor)->patch(route('supervisor.submissions.update', $submission), [
            'action' => 'save',
            'commitments' => [
                [
                    'id' => $commitment->id,
                    'function_type' => 'core',
                    'title' => 'Saved Function',
                    'description' => 'Saved indicator',
                    'weight' => 45,
                    'annual_office_target' => '15',
                    'individual_annual_targets' => '7',
                ],
                [
                    'id' => null,
                    'function_type' => 'strategic',
                    'title' => 'Added Strategic',
                    'description' => 'New row',
                    'weight' => 20,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Package updates saved.');

        $submission->refresh();
        $this->assertSame(SubmissionStatus::InReview, $submission->status);
        $this->assertNull($submission->reviewed_at);

        $commitment->refresh();
        $this->assertSame('Saved Function', $commitment->title);
        $this->assertSame('Saved indicator', $commitment->description);
        $this->assertEquals(45.0, (float) $commitment->weight);
        $this->assertSame('15', $commitment->annual_office_target);
        $this->assertSame('7', $commitment->individual_annual_targets);
        $this->assertSame(CommitmentStatus::InReview, $commitment->status);

        $this->assertDatabaseHas('commitments', [
            'ipcr_submission_id' => $submission->id,
            'title' => 'Added Strategic',
            'status' => CommitmentStatus::InReview->value,
        ]);
    }
}
