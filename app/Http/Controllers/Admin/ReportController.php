<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function ratings(Request $request): Response
    {
        $month = $request->filled('month') ? $request->string('month')->toString() : null;
        if ($month !== null && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = null;
        }

        return Inertia::render('Admin/Reports/Ratings', [
            'submissions' => $this->approvedSubmissionsList($month),
            'reviewMonths' => $this->approvedReviewMonths(),
            'filterMonth' => $month,
        ]);
    }

    public function showSubmission(IpcrSubmission $submission): Response
    {
        abort_unless($submission->status === SubmissionStatus::Approved, 404);

        $submission->load(['commitments', 'employee:id,name,email', 'supervisor:id,name']);

        return Inertia::render('Admin/Reports/SubmissionShow', [
            'submission' => $submission,
        ]);
    }

    public function usersCsv(): StreamedResponse
    {
        $filename = 'iperform-users-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'role', 'account_status', 'supervisor_email']);

            User::query()
                ->with('supervisor:id,email')
                ->orderBy('name')
                ->chunk(200, function ($users) use ($out) {
                    foreach ($users as $u) {
                        fputcsv($out, [
                            $u->name,
                            $u->email,
                            $u->role->value,
                            $u->account_status->value,
                            $u->supervisor?->email,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, IpcrSubmission>
     */
    public function approvedSubmissionsList(?string $month = null)
    {
        $query = IpcrSubmission::query()
            ->where('status', SubmissionStatus::Approved)
            ->with(['employee:id,name,email', 'supervisor:id,name']);

        if ($month !== null && preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)) {
            $query
                ->whereYear('reviewed_at', (int) $matches[1])
                ->whereMonth('reviewed_at', (int) $matches[2]);
        }

        return $query
            ->orderByDesc('reviewed_at')
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function approvedReviewMonths()
    {
        return IpcrSubmission::query()
            ->where('status', SubmissionStatus::Approved)
            ->whereNotNull('reviewed_at')
            ->orderByDesc('reviewed_at')
            ->pluck('reviewed_at')
            ->map(fn ($reviewedAt) => $reviewedAt->format('Y-m'))
            ->unique()
            ->values();
    }
}
