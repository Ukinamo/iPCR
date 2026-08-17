<?php

namespace App\Http\Controllers;

use App\Enums\CommitmentStatus;
use App\Enums\ReviewTransferRequestStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Enums\TransferRequestStatus;
use App\Models\IpcrSubmission;
use App\Models\SubmissionReviewTransferRequest;
use App\Models\SupervisorTransferRequest;
use App\Models\User;
use App\Http\Controllers\Admin\FormTemplateController;
use App\Http\Controllers\Admin\ReportController;
use App\Services\AdminAnalyticsService;
use App\Services\CommitmentWeightRules;
use App\Services\IpcrFormTemplateProvisioner;
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

        app(IpcrFormTemplateProvisioner::class)->provisionAssignedForEmployee($user);

        $commitments = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->with('accomplishments')
            ->inFormOrder()
            ->get();
        $approvedHistory = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor:id,name,email'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->take(20)
            ->get();

        $packageStatuses = Commitment::query()
            ->where('user_id', $user->id)
            ->get(['evaluation_year', 'evaluation_quarter', 'status'])
            ->groupBy(fn (Commitment $c) => $c->evaluation_year.'-'.$c->evaluation_quarter)
            ->map(function ($rows) {
                if ($rows->contains(fn (Commitment $c) => $c->status === CommitmentStatus::InReview)) {
                    return 'in_review';
                }
                if ($rows->contains(fn (Commitment $c) => $c->status === CommitmentStatus::Approved)) {
                    return 'approved';
                }
                if ($rows->contains(fn (Commitment $c) => $c->status === CommitmentStatus::Returned)) {
                    return 'returned';
                }

                return 'draft';
            });

        $activeCommitments = $packageStatuses->filter(fn ($status) => in_array($status, ['draft', 'returned'], true))->count();
        $pendingReview = $packageStatuses->filter(fn ($status) => $status === 'in_review')->count();
        $approved = $packageStatuses->filter(fn ($status) => $status === 'approved')->count();
        $approvalRate = $packageStatuses->isEmpty()
            ? 0
            : (int) round(($approved / $packageStatuses->count()) * 100);

        $submission = IpcrSubmission::query()
            ->with('commitments')
            ->where('employee_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->first();

        $weightSummary = CommitmentWeightRules::summaryForEmployee($user->id, $year, $quarter);

        $hasAssignedForm = $commitments->isNotEmpty();
        $hasAccomplishments = $commitments->contains(function ($c) {
            return $c->rating_q3_actual !== null
                || $c->rating_q4_actual !== null
                || $c->rating_actual_total !== null;
        });
        $hasDraftOrReturned = $commitments->contains(fn ($c) => in_array($c->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true));
        $submissionAllowsSubmit = ! $submission || $submission->status === SubmissionStatus::Returned;
        $canSubmitPeriod = $hasDraftOrReturned
            && $hasAssignedForm
            && $hasAccomplishments
            && $submissionAllowsSubmit
            && $weightSummary['meets_submit_requirement']
            && $user->supervisor_id !== null;

        $packageLocked = $submission && in_array($submission->status, [SubmissionStatus::InReview, SubmissionStatus::Approved], true);
        $canAnswerForm = $hasAssignedForm && $hasDraftOrReturned && ! $packageLocked;
        $addCommitmentBlockedReason = $hasAssignedForm
            ? null
            : ($user->supervisor_id === null
                ? 'Ask your administrator to assign a supervisor, then they will assign an IPCR form for you to complete.'
                : 'Waiting for an administrator to create the IPCR form and assign it to your supervisor.');
        $submitSteps = $submission && $submission->status === SubmissionStatus::Approved
            ? []
            : $this->submitStepsForEmployee(
                $user,
                $hasAssignedForm,
                $hasAccomplishments,
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
            'canAnswerForm' => $canAnswerForm,
            'hasAssignedForm' => $hasAssignedForm,
            'addCommitmentBlockedReason' => $addCommitmentBlockedReason,
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
        bool $hasAssignedForm,
        bool $hasAccomplishments,
        bool $packageLocked,
        array $weightSummary,
        bool $submissionAllowsSubmit,
        ?IpcrSubmission $submission,
    ): array {
        $supervisorOk = $user->supervisor_id !== null;
        $formStepDone = $hasAssignedForm || $packageLocked;
        $answersDone = ($hasAccomplishments && $weightSummary['meets_submit_requirement']) || $packageLocked;
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
                    : 'Ask your administrator to assign a supervisor to your account (User Management). You cannot receive a form without this.',
                'done' => $supervisorOk,
            ],
            [
                'key' => 'form',
                'title' => 'Wait for the assigned IPCR form',
                'detail' => $formStepDone
                    ? 'Your administrator assigned the form your team will use for this period.'
                    : 'An administrator must create the IPCR form (Function, Indicators, Weight, Targets) and assign it to your supervisor.',
                'done' => $formStepDone,
            ],
            [
                'key' => 'answers',
                'title' => 'Fill accomplishments (rating, average, and remarks auto-compute)',
                'detail' => $answersDone
                    ? ($packageLocked ? 'Your answers are in the system for this period.' : null)
                    : 'Enter accomplishments on the form. Rating, average, and remarks (weight × average) compute automatically.',
                'done' => $answersDone,
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
            ->where('supervisor_id', $user->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->take(50)
            ->get();

        $approved = $submissions->where('status', SubmissionStatus::Approved)->count();
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
                'teamMembers' => $teamIds->count(),
                'approved' => $approved,
                'pendingReview' => $needsReview,
                'otherActive' => $pending,
                'averageRating' => round($avgRating, 1),
            ],
            'submissions' => $submissions,
            'teamMembers' => User::query()
                ->where('supervisor_id', $user->id)
                ->where('role', UserRole::Employee)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'supervisors' => User::query()
                ->where('role', UserRole::Supervisor)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'transferRequests' => SupervisorTransferRequest::query()
                ->with(['employee:id,name,email', 'toSupervisor:id,name'])
                ->where('requested_by_id', $user->id)
                ->where('status', TransferRequestStatus::Pending)
                ->orderByDesc('created_at')
                ->get(),
            'incomingReviewTransfers' => SubmissionReviewTransferRequest::query()
                ->with(['submission.employee:id,name', 'requestedBy:id,name', 'fromSupervisor:id,name', 'toSupervisor:id,name'])
                ->where('to_supervisor_id', $user->id)
                ->where('status', ReviewTransferRequestStatus::Pending)
                ->orderByDesc('created_at')
                ->get(),
            'outgoingReviewTransfers' => SubmissionReviewTransferRequest::query()
                ->with(['submission.employee:id,name', 'toSupervisor:id,name'])
                ->where('requested_by_id', $user->id)
                ->where('status', ReviewTransferRequestStatus::Pending)
                ->orderByDesc('created_at')
                ->get(),
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'users' => $users,
            ...app(FormTemplateController::class)->dashboardProps(),
            'approvedRatings' => app(ReportController::class)->approvedSubmissionsList(),
            'reviewMonths' => app(ReportController::class)->approvedReviewMonths(),
            'analytics' => app(AdminAnalyticsService::class)->snapshot(),
            'pendingTransferCount' => SupervisorTransferRequest::where('status', TransferRequestStatus::Pending)->count(),
            'pendingTransferRequests' => SupervisorTransferRequest::query()
                ->with(['employee:id,name,email', 'requestedBy:id,name,email', 'fromSupervisor:id,name', 'toSupervisor:id,name'])
                ->where('status', TransferRequestStatus::Pending)
                ->orderBy('created_at')
                ->get(),
            'recentTransferRequests' => SupervisorTransferRequest::query()
                ->with(['employee:id,name,email', 'requestedBy:id,name,email', 'fromSupervisor:id,name', 'toSupervisor:id,name', 'reviewedBy:id,name'])
                ->whereIn('status', [TransferRequestStatus::Approved, TransferRequestStatus::Rejected, TransferRequestStatus::Cancelled])
                ->orderByDesc('reviewed_at')
                ->orderByDesc('updated_at')
                ->take(20)
                ->get(),
            'supervisors' => User::query()
                ->where('role', UserRole::Supervisor)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }
}
