<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Services\IpcrSubmissionExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeRatingController extends Controller
{
    public function show(User $user): Response
    {
        abort_unless($user->role === UserRole::Employee, 404);

        return Inertia::render('Admin/EmployeeRatings', [
            'employee' => $user->only(['id', 'name', 'email']),
            'submissions' => $this->approvedSubmissionsFor($user),
        ]);
    }

    public function export(Request $request, User $user, string $format = 'xlsx'): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        abort_unless($user->role === UserRole::Employee, 404);

        return IpcrSubmissionExportService::downloadEmployeeHistory(
            $this->approvedSubmissionsFor($user),
            $user,
            $format,
        );
    }

    public function print(Request $request, User $user): HttpResponse
    {
        abort_unless($user->role === UserRole::Employee, 404);

        return IpcrSubmissionExportService::inlinePrintEmployeeHistory(
            $this->approvedSubmissionsFor($user),
            $user,
        );
    }

    public function exportSubmission(Request $request, IpcrSubmission $submission, string $format = 'xlsx'): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $submission = IpcrSubmissionExportService::authorizeAdminExport($request, $submission);

        return IpcrSubmissionExportService::download($submission, $format);
    }

    public function printSubmission(Request $request, IpcrSubmission $submission): HttpResponse
    {
        $submission = IpcrSubmissionExportService::authorizeAdminExport($request, $submission);

        return IpcrSubmissionExportService::inlinePrint($submission);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, IpcrSubmission>
     */
    private function approvedSubmissionsFor(User $user)
    {
        return IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->get();
    }
}
