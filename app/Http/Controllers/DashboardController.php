<?php

namespace App\Http\Controllers;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
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

        $commitments = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->with('accomplishments')
            ->orderByDesc('id')
            ->get();
        $approvedHistory = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor:id,name,email'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->take(20)
            ->get();

        $activeCommitments = $commitments->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::InReview])->count();
        $pendingReview = $commitments->where('status', CommitmentStatus::InReview)->count();
        $approved = $commitments->where('status', CommitmentStatus::Approved)->count();
        $approvalRate = $commitments->isEmpty()
            ? 0
            : (int) round(($approved / max($commitments->count(), 1)) * 100);

        $submission = IpcrSubmission::query()
            ->with('commitments')
            ->where('employee_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->first();

        $weightSummary = CommitmentWeightRules::summaryForEmployee($user->id, $year, $quarter);

        $hasDraftOrReturned = $commitments->contains(fn ($c) => in_array($c->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true));
        $submissionAllowsSubmit = ! $submission || $submission->status === SubmissionStatus::Returned;
        $canSubmitPeriod = $hasDraftOrReturned
            && $submissionAllowsSubmit
            && $weightSummary['meets_submit_requirement']
            && $user->supervisor_id !== null;

        $packageLocked = $submission && in_array($submission->status, [SubmissionStatus::InReview, SubmissionStatus::Approved], true);
        $submitSteps = $submission && $submission->status === SubmissionStatus::Approved
            ? []
            : $this->submitStepsForEmployee(
                $user,
                $hasDraftOrReturned,
                $packageLocked,
                $weightSummary,
                $submissionAllowsSubmit,
                $submission,
            );

        return Inertia::render('Employee/Dashboard', [
            'stats' => [
                'activeCommitments' => $activeCommitments,
                'pendingReview' => $pendingReview,
                'approvalRate' => $approvalRate,
            ],
            'commitments' => $commitments,
            'approvedHistory' => $approvedHistory,
            'period' => [
                'label' => 'Q'.$quarter.' '.$year,
                'year' => $year,
                'quarter' => $quarter,
            ],
            'submission' => $submission,
            'weightSummary' => $weightSummary,
            'canSubmitPeriod' => $canSubmitPeriod,
            'submitSteps' => $submitSteps,
            'reminder' => 'The Q'.$quarter.' '.$year.' evaluation period closes on the last day of the quarter. Submit accomplishments and supporting documents before the deadline.',
        ]);
    }

    /**
     * Ordered checklist so employees see what to complete before "Submit for supervisor review".
     *
     * @return list<array{key: string, title: string, detail: ?string, done: bool}>
     */
    private function submitStepsForEmployee(
        User $user,
        bool $hasDraftOrReturned,
        bool $packageLocked,
        array $weightSummary,
        bool $submissionAllowsSubmit,
        ?IpcrSubmission $submission,
    ): array {
        $supervisorOk = $user->supervisor_id !== null;

        $commitmentsStepDone = $hasDraftOrReturned || $packageLocked;

        $weightsOk = $weightSummary['meets_submit_requirement'] || $packageLocked;

        $packageStepDone = $submissionAllowsSubmit;

        $packageDetail = null;
        if ($submission?->status === SubmissionStatus::InReview) {
            $packageDetail = 'Your IPCR package is already with your supervisor. Wait for approval or for it to be returned before you can submit again.';
        } elseif ($submission?->status === SubmissionStatus::Pending) {
            $packageDetail = 'Finish the steps above, then click Submit for supervisor review.';
        }

        return [
            [
                'key' => 'supervisor',
                'title' => 'Be linked to a supervisor',
                'detail' => $supervisorOk
                    ? null
                    : 'Ask your administrator to assign a supervisor to your account (User Management). You cannot submit without this.',
                'done' => $supervisorOk,
            ],
            [
                'key' => 'commitments',
                'title' => 'Add commitments for this quarter',
                'detail' => $commitmentsStepDone
                    ? ($packageLocked ? 'Your targets are in the system for this period.' : null)
                    : 'Create at least one commitment in Draft (or edit commitments your supervisor returned).',
                'done' => $commitmentsStepDone,
            ],
            [
                'key' => 'weights',
                'title' => 'Set weights to '.CommitmentWeightRules::CORE_CAP.'% core and '.CommitmentWeightRules::STRATEGIC_CAP.'% strategic',
                'detail' => $weightsOk
                    ? ($packageLocked ? 'Weights were valid when your package was sent.' : null)
                    : sprintf(
                        'Across all Draft and Returned commitments, core must total %.0f%% and strategic %.0f%% (currently %.2f%% / %.2f%%). Edit weights or add rows until it matches.',
                        CommitmentWeightRules::CORE_CAP,
                        CommitmentWeightRules::STRATEGIC_CAP,
                        $weightSummary['core'],
                        $weightSummary['strategic'],
                    ),
                'done' => $weightsOk,
            ],
            [
                'key' => 'submit',
                'title' => 'Send package for supervisor review',
                'detail' => $packageDetail,
                'done' => $packageStepDone,
            ],
        ];
    }

    private function supervisorDashboard(User $user): Response
    {
        $teamIds = User::query()
            ->where('supervisor_id', $user->id)
            ->where('role', UserRole::Employee)
            ->pluck('id');

        $submissions = IpcrSubmission::query()
            ->with(['employee', 'commitments.accomplishments'])
            ->whereIn('employee_id', $teamIds)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->take(50)
            ->get();

        $approved = $submissions->where('status', SubmissionStatus::Approved)->count();
        $pending = $submissions
            ->filter(fn ($s) => in_array($s->status, [SubmissionStatus::Pending, SubmissionStatus::InReview], true))
            ->count();

        $avgRating = (float) IpcrSubmission::query()
            ->whereIn('employee_id', $teamIds)
            ->where('status', SubmissionStatus::Approved)
            ->whereNotNull('overall_rating')
            ->avg('overall_rating') ?? 0;

        return Inertia::render('Supervisor/Dashboard', [
            'stats' => [
                'teamMembers' => $teamIds->count(),
                'approved' => $approved,
                'pendingReview' => $pending,
                'averageRating' => round($avgRating, 1),
            ],
            'submissions' => $submissions,
        ]);
    }

    private function administratorDashboard(User $user): Response
    {
        $users = User::query()->orderBy('name')->get();

        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('account_status', 'active')->count(),
            'supervisors' => User::where('role', UserRole::Supervisor)->count(),
            'employees' => User::where('role', UserRole::Employee)->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'users' => $users,
            'supervisors' => User::query()
                ->where('role', UserRole::Supervisor)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }
}
