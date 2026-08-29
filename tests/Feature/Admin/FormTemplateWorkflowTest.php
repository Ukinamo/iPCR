<?php

namespace Tests\Feature\Admin;

use App\Enums\CommitmentStatus;
use App\Enums\FormTemplateStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrFormTemplate;
use App\Models\IpcrFormTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTemplateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_copies_admin_form_edits_copy_and_admin_approves(): void
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
            'included_quarters' => [3, 4],
            'entries' => $this->spmsEntries(),
        ]);

        $template = IpcrFormTemplate::query()->first();
        $this->assertNotNull($template);
        $create->assertRedirect(route('admin.forms.show', $template));
        $create->assertSessionHasNoErrors();
        $this->assertSame(FormTemplateStatus::Draft, $template->status);
        $this->assertEquals([3, 4], array_map('intval', $template->included_quarters));
        $this->assertSame('Q3, Q4 '.$year, $template->period_label);
        $this->assertSame(2, $template->items()->count());
        $this->assertDatabaseCount('commitments', 0);

        $copy = $this->actingAs($employee)->post(route('employee.packages.from-template'), [
            'template_id' => $template->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
        ]);
        $copy->assertSessionHasNoErrors();

        $core = Commitment::query()
            ->where('user_id', $employee->id)
            ->where('function_type', 'core')
            ->first();
        $strategic = Commitment::query()
            ->where('user_id', $employee->id)
            ->where('function_type', 'strategic')
            ->first();
        $this->assertNotNull($core);
        $this->assertNull($core->ipcr_form_template_item_id);

        $this->actingAs($employee)->patch(route('employee.packages.update', $core->ipcr_submission_id), [
            'title' => 'My edited copy',
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'entries' => [
                [
                    'id' => $core->id,
                    'function_type' => 'core',
                    'title' => 'Development of Standards',
                    'description' => 'Employee edited indicator',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                ],
                [
                    'id' => $strategic->id,
                    'function_type' => 'strategic',
                    'title' => 'Strategic Project',
                    'description' => 'Partnerships',
                    'weight' => 40,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $core->refresh();
        $this->assertSame('Employee edited indicator', $core->description);
        $this->assertSame(
            'Curricula evaluated',
            IpcrFormTemplateItem::query()->where('function_type', 'core')->value('description'),
        );

        $answers = $this->actingAs($employee)->patch(route('employee.form-answers.update'), [
            'submission_id' => $core->ipcr_submission_id,
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
            'submission_id' => $core->ipcr_submission_id,
        ]);
        $submit->assertRedirect();
        $submit->assertSessionHasNoErrors();

        $submission = $employee->ipcrSubmissionsAsEmployee()->first();
        $this->assertSame(SubmissionStatus::InReview, $submission->status);

        $review = $this->actingAs($admin)->patch(route('admin.submissions.update', $submission), [
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

    public function test_legacy_commitment_store_cannot_create_form_structure(): void
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

    public function test_employee_can_create_own_form_and_submit_many_packages(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
            'supervisor_id' => $supervisor->id,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $this->actingAs($employee)->post(route('employee.packages.store'), [
            'title' => 'Own form A',
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'entries' => $this->spmsEntries(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->post(route('employee.packages.store'), [
            'title' => 'Own form B',
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'entries' => $this->spmsEntries('Alt Core', 'Alt Strategic'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $employee->ipcrSubmissionsAsEmployee()->count());
        $this->assertSame(4, Commitment::query()->where('user_id', $employee->id)->count());
    }

    public function test_admin_can_save_form_without_function_title(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $this->actingAs($admin)->post(route('admin.forms.store'), [
            'title' => 'Untitled functions',
            'evaluation_year' => $year,
            'included_quarters' => [3, 4],
            'entries' => [
                [
                    'function_type' => 'core',
                    'title' => null,
                    'description' => 'Indicator A',
                    'weight' => 60,
                    'annual_office_target' => '10',
                    'individual_annual_targets' => '5',
                ],
                [
                    'function_type' => 'strategic',
                    'title' => '',
                    'description' => 'Indicator B',
                    'weight' => 40,
                    'annual_office_target' => '4',
                    'individual_annual_targets' => '2',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ipcr_form_template_items', [
            'function_type' => 'core',
            'title' => null,
            'description' => 'Indicator A',
        ]);
        $this->assertDatabaseHas('ipcr_form_template_items', [
            'function_type' => 'strategic',
            'title' => null,
            'description' => 'Indicator B',
        ]);
    }

    public function test_untitled_core_functions_keep_separate_groups_and_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $year = (int) now()->year;

        $this->actingAs($admin)->post(route('admin.forms.store'), [
            'title' => 'Two blank cores',
            'evaluation_year' => $year,
            'included_quarters' => [3, 4],
            'entries' => [
                [
                    'function_type' => 'core',
                    'function_group' => 0,
                    'sort_order' => 0,
                    'title' => '',
                    'description' => 'First untitled',
                    'weight' => 30,
                    'annual_office_target' => '1',
                    'individual_annual_targets' => '1',
                ],
                [
                    'function_type' => 'core',
                    'function_group' => 1,
                    'sort_order' => 1,
                    'title' => '',
                    'description' => 'Second untitled',
                    'weight' => 30,
                    'annual_office_target' => '2',
                    'individual_annual_targets' => '2',
                ],
                [
                    'function_type' => 'strategic',
                    'function_group' => 2,
                    'sort_order' => 2,
                    'title' => '',
                    'description' => 'Strategic untitled',
                    'weight' => 40,
                    'annual_office_target' => '3',
                    'individual_annual_targets' => '3',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $template = IpcrFormTemplate::query()->first();
        $cores = $template->items()->where('function_type', 'core')->orderBy('sort_order')->get();
        $this->assertSame(['First untitled', 'Second untitled'], $cores->pluck('description')->all());
        $this->assertNotSame($cores[0]->function_group, $cores[1]->function_group);

        $this->actingAs($admin)->patch(route('admin.forms.update', $template), [
            'title' => 'Two blank cores',
            'evaluation_year' => $year,
            'included_quarters' => [3, 4],
            'entries' => [
                [
                    'id' => $cores[1]->id,
                    'function_type' => 'core',
                    'function_group' => 0,
                    'sort_order' => 0,
                    'title' => '',
                    'description' => 'Second untitled',
                    'weight' => 30,
                    'annual_office_target' => '2',
                    'individual_annual_targets' => '2',
                ],
                [
                    'id' => $cores[0]->id,
                    'function_type' => 'core',
                    'function_group' => 1,
                    'sort_order' => 1,
                    'title' => '',
                    'description' => 'First untitled',
                    'weight' => 30,
                    'annual_office_target' => '1',
                    'individual_annual_targets' => '1',
                ],
                [
                    'id' => $template->items()->where('function_type', 'strategic')->value('id'),
                    'function_type' => 'strategic',
                    'function_group' => 2,
                    'sort_order' => 2,
                    'title' => '',
                    'description' => 'Strategic untitled',
                    'weight' => 40,
                    'annual_office_target' => '3',
                    'individual_annual_targets' => '3',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $reordered = $template->items()->where('function_type', 'core')->orderBy('sort_order')->pluck('description')->all();
        $this->assertSame(['Second untitled', 'First untitled'], $reordered);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spmsEntries(string $coreTitle = 'Development of Standards', string $strategicTitle = 'Strategic Project'): array
    {
        return [
            [
                'function_type' => 'core',
                'title' => $coreTitle,
                'description' => $coreTitle === 'Development of Standards' ? 'Curricula evaluated' : 'Indicator A',
                'weight' => 60,
                'annual_office_target' => '10',
                'individual_annual_targets' => '5',
            ],
            [
                'function_type' => 'strategic',
                'title' => $strategicTitle,
                'description' => $strategicTitle === 'Strategic Project' ? 'Partnerships' : 'Indicator B',
                'weight' => 40,
                'annual_office_target' => '4',
                'individual_annual_targets' => '2',
            ],
        ];
    }
}
