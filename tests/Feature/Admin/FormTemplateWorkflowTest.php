<?php

namespace Tests\Feature\Admin;

use App\Enums\CommitmentStatus;
use App\Enums\FormTemplateStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrFormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTemplateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_form_and_employee_fills_then_supervisor_approves(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $create = $this->actingAs($admin)->post(route('admin.forms.store'), [
            'title' => 'Team IPCR',
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => 'Q'.$quarter.' '.$year,
            'entries' => [
                [
                    'function_type' => 'core',
                    'title' => 'Development of Standards',
                    'description' => 'Curricula evaluated',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                ],
                [
                    'function_type' => 'strategic',
                    'title' => 'Strategic Project',
                    'description' => 'Partnerships',
                    'weight' => 40,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ]);

        $template = IpcrFormTemplate::query()->first();
        $this->assertNotNull($template);
        $create->assertRedirect(route('admin.forms.show', $template));
        $create->assertSessionHasNoErrors();
        $this->assertSame(FormTemplateStatus::Draft, $template->status);
        $this->assertSame(2, $template->items()->count());

        $assign = $this->actingAs($admin)->post(route('admin.forms.assign', $template), [
            'supervisor_ids' => [$supervisor->id],
        ]);
        $assign->assertRedirect();
        $assign->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame(FormTemplateStatus::Assigned, $template->status);
        $this->assertTrue($template->supervisors()->where('users.id', $supervisor->id)->exists());

        $this->assertDatabaseHas('commitments', [
            'user_id' => $employee->id,
            'title' => 'Development of Standards',
            'weight' => 60,
            'status' => CommitmentStatus::Draft->value,
        ]);

        $core = Commitment::query()
            ->where('user_id', $employee->id)
            ->where('function_type', 'core')
            ->first();
        $strategic = Commitment::query()
            ->where('user_id', $employee->id)
            ->where('function_type', 'strategic')
            ->first();

        $answers = $this->actingAs($employee)->patch(route('employee.form-answers.update'), [
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'commitments' => [
                [
                    'id' => $core->id,
                    'rating_q3_target' => 2,
                    'rating_q3_actual' => 2,
                    'rating_q4_target' => 3,
                    'rating_q4_actual' => 3,
                ],
                [
                    'id' => $strategic->id,
                    'rating_q3_target' => 1,
                    'rating_q3_actual' => 1,
                    'rating_q4_target' => 1,
                    'rating_q4_actual' => 1,
                ],
            ],
        ]);

        $answers->assertRedirect();
        $answers->assertSessionHasNoErrors();

        $core->refresh();
        $this->assertSame(3, (int) $core->rating_quality);
        $this->assertEquals(3.0, (float) $core->rating_average);
        $this->assertEquals(1.8, (float) $core->rating_weighted);
        $this->assertSame('1.80', $core->remarks);

        $submit = $this->actingAs($employee)->post(route('employee.submissions.store'), [
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
        ]);
        $submit->assertRedirect();
        $submit->assertSessionHasNoErrors();

        $submission = $employee->ipcrSubmissionsAsEmployee()->first();
        $this->assertSame(SubmissionStatus::InReview, $submission->status);

        $review = $this->actingAs($supervisor)->patch(route('supervisor.submissions.update', $submission), [
            'action' => 'approve',
            'supervisor_feedback' => 'Approved with edits.',
            'commitments' => [
                [
                    'id' => $core->id,
                    'function_type' => 'core',
                    'title' => 'Development of Standards',
                    'description' => 'Curricula evaluated (revised)',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                    'rating_q3_target' => 2,
                    'rating_q3_actual' => 2,
                    'rating_q4_target' => 3,
                    'rating_q4_actual' => 3,
                    'rating_quality' => 4,
                    'rating_efficiency' => 4,
                    'rating_timeliness' => 4,
                ],
                [
                    'id' => $strategic->id,
                    'function_type' => 'strategic',
                    'title' => 'Strategic Project',
                    'description' => 'Partnerships',
                    'weight' => 40,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                    'rating_q3_target' => 1,
                    'rating_q3_actual' => 1,
                    'rating_q4_target' => 1,
                    'rating_q4_actual' => 1,
                    'rating_quality' => 3,
                    'rating_efficiency' => 3,
                    'rating_timeliness' => 3,
                ],
            ],
        ]);

        $review->assertRedirect();
        $review->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertEquals(3.6, (float) $submission->overall_rating);

        $core->refresh();
        $this->assertSame('Curricula evaluated (revised)', $core->description);
        $this->assertEquals(4.0, (float) $core->rating_average);
        $this->assertEquals(2.4, (float) $core->rating_weighted);
        $this->assertSame('2.40', $core->remarks);
    }

    public function test_employee_cannot_create_form_structure(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

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
        $this->assertDatabaseCount('commitments', 0);
    }

    public function test_admin_can_assign_one_form_to_multiple_supervisors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisorA = User::factory()->create(['role' => UserRole::Supervisor]);
        $supervisorB = User::factory()->create(['role' => UserRole::Supervisor]);
        $employeeA = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorA->id,
        ]);
        $employeeB = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisorB->id,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $this->actingAs($admin)->post(route('admin.forms.store'), [
            'title' => 'Shared IPCR',
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => 'Q'.$quarter.' '.$year,
            'entries' => [
                [
                    'function_type' => 'core',
                    'title' => 'Core Work',
                    'description' => 'Indicator A',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                ],
                [
                    'function_type' => 'strategic',
                    'title' => 'Strategic Work',
                    'description' => 'Indicator B',
                    'weight' => 40,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $template = IpcrFormTemplate::query()->first();

        $this->actingAs($admin)
            ->post(route('admin.forms.assign', $template), [
                'supervisor_ids' => [$supervisorA->id, $supervisorB->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $template->supervisors()->count());
        $this->assertDatabaseHas('commitments', [
            'user_id' => $employeeA->id,
            'title' => 'Core Work',
        ]);
        $this->assertDatabaseHas('commitments', [
            'user_id' => $employeeB->id,
            'title' => 'Core Work',
        ]);
    }
}
