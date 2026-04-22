<?php

namespace App\Http\Controllers;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
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
            ->orderByDesc('id')
            ->get();

        $accomplishments = $user->accomplishments()->latest()->take(20)->get();

        $activeCommitments = $commitments->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::InReview])->count();
        $pendingReview = $commitments->where('status', CommitmentStatus::InReview)->count();
        $approved = $commitments->where('status', CommitmentStatus::Approved)->count();
        $approvalRate = $commitments->isEmpty()
            ? 0
            : (int) round(($approved / max($commitments->count(), 1)) * 100);

        $submission = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->first();

        return Inertia::render('Employee/Dashboard', [
            'stats' => [
                'activeCommitments' => $activeCommitments,
                'pendingReview' => $pendingReview,
                'approvalRate' => $approvalRate,
            ],
            'commitments' => $commitments,
            'accomplishments' => $accomplishments,
            'period' => [
                'label' => 'Q'.$quarter.' '.$year,
                'year' => $year,
                'quarter' => $quarter,
            ],
            'submission' => $submission,
            'reminder' => 'The Q'.$quarter.' '.$year.' evaluation period closes on the last day of the quarter. Submit accomplishments and supporting documents before the deadline.',
        ]);
    }

    private function supervisorDashboard(User $user): Response
    {
        $teamIds = User::query()
            ->where('supervisor_id', $user->id)
            ->where('role', UserRole::Employee)
            ->pluck('id');

        $submissions = IpcrSubmission::query()
            ->with('employee')
            ->whereIn('employee_id', $teamIds)
            ->orderByDesc('submitted_at')
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
