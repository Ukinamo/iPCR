<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Services\SpmsRatingCalculator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->administrator()->create([
            'name' => 'Rey Administrator',
            'email' => 'rey@admin.com',
            'password' => 'password',
        ]);

        $supervisor = User::factory()->supervisor()->create([
            'name' => 'Rey Supervisor',
            'email' => 'rey@supervisor.com',
            'password' => 'password',
        ]);

        $maria = User::factory()->employee()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@ched.gov.ph',
            'password' => 'password',
            'supervisor_id' => $supervisor->id,
        ]);

        $juan = User::factory()->employee()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@ched.gov.ph',
            'password' => 'password',
            'supervisor_id' => $supervisor->id,
        ]);

        $ana = User::factory()->employee()->create([
            'name' => 'Ana Rodriguez',
            'email' => 'ana.rodriguez@ched.gov.ph',
            'password' => 'password',
            'supervisor_id' => $supervisor->id,
        ]);

        User::factory()->employee()->create([
            'name' => 'Carlos Lopez',
            'email' => 'carlos.lopez@ched.gov.ph',
            'password' => 'password',
            'supervisor_id' => $supervisor->id,
            'account_status' => AccountStatus::Inactive,
        ]);

        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);
        $periodLabel = 'Q'.$quarter.' '.$year;

        $mariaSubmission = IpcrSubmission::create([
            'employee_id' => $maria->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'status' => SubmissionStatus::InReview,
            'submitted_at' => now()->subDays(3),
        ]);

        Commitment::create([
            'user_id' => $maria->id,
            'ipcr_submission_id' => $mariaSubmission->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => $periodLabel,
            'title' => 'Conduct staff training on IPCR system',
            'description' => 'Roll out I-PERFORM orientation to regional HEI focal persons; track attendance sheets.',
            'function_type' => 'core',
            'weight' => 60,
            'progress' => 100,
            'status' => CommitmentStatus::InReview,
        ]);

        Commitment::create([
            'user_id' => $maria->id,
            'ipcr_submission_id' => $mariaSubmission->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => $periodLabel,
            'title' => 'Prepare institutional performance report',
            'description' => 'Consolidate Q outputs vs targets for MIMAROPA HEIs; align narrative with SPMS indicators.',
            'function_type' => 'strategic',
            'weight' => 40,
            'progress' => 45,
            'status' => CommitmentStatus::InReview,
        ]);

        $juanSubmission = IpcrSubmission::create([
            'employee_id' => $juan->id,
            'supervisor_id' => $supervisor->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'status' => SubmissionStatus::Approved,
            'quality' => 5,
            'efficiency' => 4,
            'timeliness' => 5,
            'overall_rating' => SpmsRatingCalculator::overall(5, 4, 5),
            'submitted_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(2),
        ]);

        Commitment::create([
            'user_id' => $juan->id,
            'ipcr_submission_id' => $juanSubmission->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => $periodLabel,
            'title' => 'Regional monitoring visits',
            'description' => 'Field validation of compliance indicators across sample HEIs.',
            'function_type' => 'core',
            'weight' => 60,
            'progress' => 100,
            'status' => CommitmentStatus::Approved,
        ]);

        Commitment::create([
            'user_id' => $juan->id,
            'ipcr_submission_id' => $juanSubmission->id,
            'evaluation_year' => $year,
            'evaluation_quarter' => $quarter,
            'period_label' => $periodLabel,
            'title' => 'Stakeholder engagement and reporting',
            'description' => 'Quarterly synthesis of policy feedback and risk notes for regional council.',
            'function_type' => 'strategic',
            'weight' => 40,
            'progress' => 100,
            'status' => CommitmentStatus::Approved,
        ]);

        $this->command?->info('I-PERFORM demo (password: password)');
        $this->command?->info('Administrator: rey@admin.com');
        $this->command?->info('Supervisor: rey@supervisor.com');
        $this->command?->info('Employees: maria.santos@ched.gov.ph, juan.delacruz@ched.gov.ph, ana.rodriguez@ched.gov.ph');
    }
}
