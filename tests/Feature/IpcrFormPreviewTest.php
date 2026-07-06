<?php

namespace Tests\Feature;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Services\IpcrFormViewDataBuilder;
use App\Services\IpcrSubmissionExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrFormPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_open_preview_and_update_commitment_statement(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'status' => SubmissionStatus::Approved,
            'overall_rating' => 4.25,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        Commitment::query()->create([
            'user_id' => $employee->id,
            'ipcr_submission_id' => $submission->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 3,
            'period_label' => 'Q3 2025',
            'function_type' => 'core',
            'title' => 'Development of Standards',
            'description' => "No. of Curricula evaluated per PSGs",
            'weight' => 30,
            'annual_office_target' => '10',
            'individual_annual_targets' => '5',
            'rating_q3_target' => 2,
            'rating_q3_actual' => 2,
            'rating_q4_target' => 3,
            'rating_q4_actual' => 3,
            'rating_target_total' => 5,
            'rating_actual_total' => 5,
            'rating_percent' => 100,
            'rating_quality' => 4,
            'rating_efficiency' => 4,
            'rating_timeliness' => 4,
            'rating_average' => 4,
            'rating_weighted' => 1.2,
            'progress' => 100,
            'status' => CommitmentStatus::Approved,
        ]);

        $preview = $this->actingAs($employee)->get(
            route('employee.submissions.preview', $submission),
        );
        $preview->assertOk();

        $custom = 'I, Jane Doe, Education Supervisor II, of CHED-MIMAROPA Regional Office, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for the period';

        $update = $this->actingAs($employee)->patch(
            route('employee.submissions.commitment-statement', $submission),
            ['commitment_statement' => $custom],
        );
        $update->assertRedirect();

        $submission->refresh();
        $this->assertSame($custom, $submission->commitment_statement);

        $document = $this->actingAs($employee)->get(
            route('employee.submissions.document', $submission),
        );
        $document->assertOk();
        $document->assertSee('INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW FORM (IPCR)', false);
        $document->assertSee($custom, false);
        $document->assertSee('July 1, 2025 to September 30, 2025', false);
        $document->assertSee('Development of Standards', false);
        $document->assertSee('FORM 1', false);
    }

    public function test_xlsx_export_uses_ipcr_form_template(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee, 'name' => 'Juan Dela Cruz']);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $submission = IpcrSubmission::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => 2025,
            'evaluation_quarter' => 4,
            'status' => SubmissionStatus::Approved,
            'overall_rating' => 3.75,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $spreadsheet = IpcrSubmissionExportService::spreadsheet($submission);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertStringContainsString('CHED', (string) $sheet->getCell('A3')->getValue());
        $this->assertStringContainsString('commit to deliver', (string) $sheet->getCell('A5')->getValue());
        $this->assertStringContainsString('FORM 1', (string) $sheet->getCell('Q1')->getValue());
    }

    public function test_default_commitment_statement_uses_employee_name_and_office(): void
    {
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'name' => 'Maria Santos',
        ]);

        $statement = IpcrFormViewDataBuilder::defaultCommitmentStatement($employee);

        $this->assertStringContainsString('Maria Santos', $statement);
        $this->assertStringContainsString('Education Supervisor II', $statement);
        $this->assertStringContainsString('CHED', $statement);
        $this->assertStringContainsString('commit to deliver', $statement);
    }
}
