<?php

namespace Tests\Feature\Supervisor;

use App\Enums\StoMonitoringReportType;
use App\Enums\UserRole;
use App\Models\StoMonitoringForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoMonitoringFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_create_stufap_and_student_services_reports(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $stufap = $this->actingAs($supervisor)->post(route('supervisor.sto-monitoring.store'), [
            'report_type' => StoMonitoringReportType::Stufap->value,
            'title' => 'REPORT ON STO: Monitoring of HEI with STUFAPs',
            'office_name' => 'CHEDRO : MIMAROPA',
            'evaluation_year' => 2026,
            'entries' => [
                [
                    'hei_name' => 'Palawan State University',
                    'monitored_item' => 'TD',
                    'grantee_count' => 12,
                    'date_monitored' => '01/15/2026',
                    'remarks' => 'On track',
                ],
                [
                    'hei_name' => '',
                    'monitored_item' => '',
                    'grantee_count' => null,
                    'date_monitored' => '',
                    'remarks' => '',
                ],
            ],
        ]);

        $stufap->assertRedirect();

        $stufapForm = StoMonitoringForm::query()
            ->where('report_type', StoMonitoringReportType::Stufap)
            ->first();

        $this->assertNotNull($stufapForm);
        $this->assertSame(1, $stufapForm->entries()->count());
        $this->assertSame('TD', $stufapForm->entries()->first()->monitored_item);
        $this->assertSame(12, $stufapForm->entries()->first()->grantee_count);

        $services = $this->actingAs($supervisor)->post(route('supervisor.sto-monitoring.store'), [
            'report_type' => StoMonitoringReportType::StudentServices->value,
            'title' => 'REPORT ON STO: Monitoring of Student Services',
            'office_name' => 'CHEDRO : MIMAROPA',
            'evaluation_year' => 2026,
            'entries' => [
                [
                    'hei_name' => 'Occidental Mindoro State College',
                    'monitored_item' => 'General Monitoring',
                    'date_monitored' => '02/01/2026',
                    'remarks' => 'Compliant',
                ],
            ],
        ]);

        $services->assertRedirect();

        $this->assertSame(2, StoMonitoringForm::query()->where('supervisor_id', $supervisor->id)->count());
        $this->assertSame('draft', $stufapForm->fresh()->statusValue());

        $dashboard = $this->actingAs($supervisor)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertInertia(fn ($page) => $page
            ->component('Supervisor/Dashboard')
            ->has('stoMonitoringForms', 2)
            ->where('stoMonitoringForms.0.report_type', fn ($type) => in_array($type, ['stufap', 'student_services'], true))
        );
    }

    public function test_submitted_sto_report_appears_for_admin_review(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $create = $this->actingAs($supervisor)->post(route('supervisor.sto-monitoring.store'), [
            'report_type' => StoMonitoringReportType::Stufap->value,
            'title' => 'REPORT ON STO: Monitoring of HEI with STUFAPs',
            'office_name' => 'CHEDRO : MIMAROPA',
            'evaluation_year' => 2026,
            'submit' => true,
            'entries' => [
                [
                    'hei_name' => 'Palawan State University',
                    'monitored_item' => 'Scholarship',
                    'grantee_count' => 4,
                    'date_monitored' => '03/01/2026',
                    'remarks' => 'Visited',
                ],
            ],
        ]);

        $create->assertRedirect(route('dashboard', ['tab' => 'programs']));

        $form = StoMonitoringForm::query()->first();
        $this->assertSame('in_review', $form->statusValue());

        $adminDashboard = $this->actingAs($admin)->get(route('dashboard'));
        $adminDashboard->assertOk();
        $adminDashboard->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('pendingRegisterReportCount', 1)
        );

        $approve = $this->actingAs($admin)->patch(route('admin.sto-monitoring.update', $form), [
            'action' => 'approve',
        ]);
        $approve->assertRedirect();
        $this->assertSame('approved', $form->fresh()->statusValue());
    }
}
