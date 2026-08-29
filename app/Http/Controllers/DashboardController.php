<?php

namespace App\Http\Controllers;

use App\Enums\RegisterReportStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrFormTemplate;
use App\Models\IpcrSubmission;
use App\Models\ProgramEvaluationForm;
use App\Models\StoMonitoringForm;
use App\Models\User;
use App\Http\Controllers\Admin\FormTemplateController;
use App\Http\Controllers\Admin\ReportController;
use App\Services\AdminAnalyticsService;
use App\Services\CommitmentWeightRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Employee => $this->employeeDashboard($user),
            UserRole::Supervisor => $this->supervisorDashboard($user),
            UserRole::Administrator => $this->administratorDashboard($user),
        };
    }

    private function employeeDashboard(User $user): Response
    {
        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $packages = IpcrSubmission::query()
            ->with(['commitments' => fn ($q) => $q->inFormOrder()->with('accomplishments')])
            ->where('employee_id', $user->id)
            ->where('status', '!=', SubmissionStatus::Approved)
            ->orderByDesc('id')
            ->get()
            ->map(function (IpcrSubmission $package) use ($user) {
                $commitments = $package->commitments;
                $totals = CommitmentWeightRules::totalsFromRows($commitments);
                $editable = in_array($package->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true);

                return [
                    ...$package->toArray(),
                    'period_label' => \App\Support\IpcrIncludedQuarters::periodLabel(
                        (int) $package->evaluation_year,
                        $package->included_quarters,
                    ),
                    'weight_summary' => [
                        ...$totals,
                        'meets_submit_requirement' => CommitmentWeightRules::meetsSpmsSplit($totals['core'], $totals['strategic']),
                    ],
                    'can_edit' => $editable,
                    'can_delete' => $editable,
                    'can_submit' => $editable
                        && CommitmentWeightRules::meetsSpmsSplit($totals['core'], $totals['strategic'])
                        && $user->supervisor_id !== null,
                    'open_commitment_id' => $commitments->first()?->id,
                ];
            })
            ->values();

        $approvedHistory = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor:id,name,email'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->take(20)
            ->get();

        $packageStatuses = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->get(['status']);
        $pendingReview = $packageStatuses->where('status', SubmissionStatus::InReview)->count();
        $approved = $packageStatuses->where('status', SubmissionStatus::Approved)->count();
        $active = $packageStatuses->filter(fn ($row) => in_array($row->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true))->count();
        $approvalRate = $packageStatuses->isEmpty()
            ? 0
            : (int) round(($approved / $packageStatuses->count()) * 100);

        $availableTemplates = IpcrFormTemplate::query()
            ->with('items')
            ->withCount('items')
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->orderByDesc('id')
            ->get()
            ->map(function ($template) {
                $totals = CommitmentWeightRules::totalsFromRows($template->items);

                return [
                    'id' => $template->id,
                    'title' => $template->title,
                    'period_label' => $template->period_label,
                    'items_count' => $template->items_count,
                    'weight_summary' => $totals,
                ];
            });

        $blankWeightSummary = [
            'core' => 0,
            'strategic' => 0,
            'total' => 0,
            'core_remaining' => CommitmentWeightRules::CORE_CAP,
            'strategic_remaining' => CommitmentWeightRules::STRATEGIC_CAP,
            'core_cap' => CommitmentWeightRules::CORE_CAP,
            'strategic_cap' => CommitmentWeightRules::STRATEGIC_CAP,
            'meets_submit_requirement' => false,
        ];

        return Inertia::render('Employee/Dashboard', [
            'stats' => [
                'activeCommitments' => $active,
                'pendingReview' => $pendingReview,
                'approvalRate' => $approvalRate,
            ],
            'packages' => $packages,
            'availableTemplates' => $availableTemplates,
            'approvedHistory' => $approvedHistory,
            'period' => [
                'label' => 'Q'.$quarter.' '.$year,
                'year' => $year,
                'quarter' => $quarter,
            ],
            'formWeightSummary' => $blankWeightSummary,
            'reminder' => 'The Q'.$quarter.' '.$year.' evaluation period closes on the last day of the quarter. Submit accomplishments and supporting documents before the deadline.',
        ]);
    }

    private function supervisorDashboard(User $user): Response
    {
        $submissions = IpcrSubmission::query()
            ->with(['employee', 'commitments.accomplishments'])
            ->where('supervisor_id', $user->id)
            ->where('status', '!=', SubmissionStatus::Approved)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $approvedSubmissions = IpcrSubmission::query()
            ->with(['employee:id,name,email', 'commitments'])
            ->where('supervisor_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->get();

        $approved = $approvedSubmissions->count();
        $needsReview = $submissions->where('status', SubmissionStatus::InReview)->count();
        $pending = $submissions
            ->filter(fn ($s) => in_array($s->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true))
            ->count();

        $avgRating = (float) IpcrSubmission::query()
            ->where('supervisor_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->whereNotNull('overall_rating')
            ->avg('overall_rating') ?? 0;

        return Inertia::render('Supervisor/Dashboard', [
            'stats' => [
                'approved' => $approved,
                'pendingReview' => $needsReview,
                'otherActive' => $pending,
                'averageRating' => round($avgRating, 1),
            ],
            'submissions' => $submissions,
            'approvedSubmissions' => $approvedSubmissions,
            'programEvaluationForms' => ProgramEvaluationForm::query()
                ->where('supervisor_id', $user->id)
                ->withCount('entries')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (ProgramEvaluationForm $form) => [
                    'id' => $form->id,
                    'title' => $form->title,
                    'office_name' => $form->office_name,
                    'evaluation_year' => $form->evaluation_year,
                    'entries_count' => $form->entries_count,
                    'status' => $form->statusValue(),
                    'can_edit' => $form->supervisorCanEdit(),
                    'review_notes' => $form->review_notes,
                ]),
            'stoMonitoringForms' => StoMonitoringForm::query()
                ->where('supervisor_id', $user->id)
                ->withCount('entries')
                ->orderByDesc('updated_at')
                ->get()
                ->map(function (StoMonitoringForm $form) {
                    return [
                        'id' => $form->id,
                        'report_type' => $form->report_type->value,
                        'title' => $form->title,
                        'office_name' => $form->office_name,
                        'evaluation_year' => $form->evaluation_year,
                        'entries_count' => $form->entries_count,
                        'status' => $form->statusValue(),
                        'can_edit' => $form->supervisorCanEdit(),
                        'review_notes' => $form->review_notes,
                    ];
                }),
        ]);
    }

    private function administratorDashboard(User $user): Response
    {
        $users = User::query()->orderBy('name')->get();

        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('account_status', 'active')->count(),
            'pendingRegistrations' => User::where('account_status', 'pending')->count(),
            'supervisors' => User::where('role', UserRole::Supervisor)->count(),
            'employees' => User::where('role', UserRole::Employee)->count(),
        ];

        $pendingReviews = IpcrSubmission::query()
            ->with(['employee:id,name,email', 'commitments'])
            ->where('status', SubmissionStatus::InReview)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->take(50)
            ->get();

        $pendingRegisterReports = $this->pendingRegisterReports();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'users' => $users,
            ...app(FormTemplateController::class)->dashboardProps(),
            'approvedRatings' => app(ReportController::class)->approvedSubmissionsList(),
            'reviewMonths' => app(ReportController::class)->approvedReviewMonths(),
            'analytics' => app(AdminAnalyticsService::class)->snapshot(),
            'pendingReviews' => $pendingReviews,
            'pendingReviewCount' => IpcrSubmission::query()->where('status', SubmissionStatus::InReview)->count(),
            'pendingRegisterReports' => $pendingRegisterReports,
            'pendingRegisterReportCount' => count($pendingRegisterReports),
            'approvedRegisterReports' => $this->approvedRegisterReports(),
            'supervisors' => User::query()
                ->where('role', UserRole::Supervisor)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingRegisterReports(): array
    {
        $programs = ProgramEvaluationForm::query()
            ->with('supervisor:id,name,email')
            ->withCount('entries')
            ->where('status', RegisterReportStatus::InReview)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (ProgramEvaluationForm $form) => [
                'kind' => 'program_evaluation',
                'id' => $form->id,
                'title' => $form->title,
                'kind_label' => 'Programs evaluated',
                'office_name' => $form->office_name,
                'evaluation_year' => $form->evaluation_year,
                'entries_count' => $form->entries_count,
                'supervisor_name' => $form->supervisor?->name,
                'submitted_at' => $form->submitted_at?->toIso8601String(),
                'show_url' => route('admin.program-evaluations.show', $form),
            ]);

        $sto = StoMonitoringForm::query()
            ->with('supervisor:id,name,email')
            ->withCount('entries')
            ->where('status', RegisterReportStatus::InReview)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (StoMonitoringForm $form) => [
                'kind' => 'sto_monitoring',
                'id' => $form->id,
                'title' => $form->title,
                'kind_label' => $form->report_type->label(),
                'office_name' => $form->office_name,
                'evaluation_year' => $form->evaluation_year,
                'entries_count' => $form->entries_count,
                'supervisor_name' => $form->supervisor?->name,
                'submitted_at' => $form->submitted_at?->toIso8601String(),
                'show_url' => route('admin.sto-monitoring.show', $form),
            ]);

        return $programs->concat($sto)
            ->sortByDesc('submitted_at')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvedRegisterReports(): array
    {
        $programs = ProgramEvaluationForm::query()
            ->with('supervisor:id,name,email')
            ->withCount('entries')
            ->where('status', RegisterReportStatus::Approved)
            ->orderByDesc('reviewed_at')
            ->take(30)
            ->get()
            ->map(fn (ProgramEvaluationForm $form) => [
                'kind' => 'program_evaluation',
                'id' => $form->id,
                'title' => $form->title,
                'kind_label' => 'Programs evaluated',
                'evaluation_year' => $form->evaluation_year,
                'entries_count' => $form->entries_count,
                'supervisor_name' => $form->supervisor?->name,
                'reviewed_at' => $form->reviewed_at?->toIso8601String(),
                'show_url' => route('admin.program-evaluations.show', $form),
            ]);

        $sto = StoMonitoringForm::query()
            ->with('supervisor:id,name,email')
            ->withCount('entries')
            ->where('status', RegisterReportStatus::Approved)
            ->orderByDesc('reviewed_at')
            ->take(30)
            ->get()
            ->map(fn (StoMonitoringForm $form) => [
                'kind' => 'sto_monitoring',
                'id' => $form->id,
                'title' => $form->title,
                'kind_label' => $form->report_type->label(),
                'evaluation_year' => $form->evaluation_year,
                'entries_count' => $form->entries_count,
                'supervisor_name' => $form->supervisor?->name,
                'reviewed_at' => $form->reviewed_at?->toIso8601String(),
                'show_url' => route('admin.sto-monitoring.show', $form),
            ]);

        return $programs->concat($sto)
            ->sortByDesc('reviewed_at')
            ->take(40)
            ->values()
            ->all();
    }
}
