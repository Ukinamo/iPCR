<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Services\IpcrFormViewDataBuilder;
use App\Services\IpcrSubmissionExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class IpcrFormPreviewController extends Controller
{
    public function show(Request $request, IpcrSubmission $submission): Response
    {
        $submission = $this->authorizeSubmission($request, $submission);

        return Inertia::render('Ipcr/FormPreview', [
            'submission' => [
                'id' => $submission->id,
                'evaluation_year' => $submission->evaluation_year,
                'evaluation_quarter' => $submission->evaluation_quarter,
                'overall_rating' => $submission->overall_rating,
            ],
            'employee' => $submission->employee->only(['id', 'name']),
            'commitmentStatement' => IpcrFormViewDataBuilder::resolveCommitmentStatement(
                $submission,
                $submission->employee,
            ),
            'periodWindow' => IpcrFormViewDataBuilder::periodWindow($submission),
            'officeName' => config('ipcr.office_name'),
            'documentUrl' => $this->documentUrl($request, $submission),
            'printUrl' => $this->printUrl($request, $submission),
            'exportUrls' => [
                'xlsx' => $this->exportUrl($request, $submission, 'xlsx'),
                'pdf' => $this->exportUrl($request, $submission, 'pdf'),
                'csv' => $this->exportUrl($request, $submission, 'csv'),
            ],
            'updateUrl' => $this->updateUrl($request, $submission),
            'backUrl' => $this->backUrl($request),
        ]);
    }

    public function updateCommitment(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $submission = $this->authorizeSubmission($request, $submission);

        $validated = $request->validate([
            'commitment_statement' => ['required', 'string', 'max:2000'],
        ]);

        $submission->update([
            'commitment_statement' => $validated['commitment_statement'],
        ]);

        return redirect()->back()->with('success', 'Commitment statement saved.');
    }

    public function document(Request $request, IpcrSubmission $submission): HttpResponse
    {
        $submission = $this->authorizeSubmission($request, $submission);

        return response(
            IpcrSubmissionExportService::renderDocumentHtml($submission),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function print(Request $request, IpcrSubmission $submission): HttpResponse
    {
        $submission = $this->authorizeSubmission($request, $submission);

        return IpcrSubmissionExportService::inlinePrint($submission);
    }

    private function authorizeSubmission(Request $request, IpcrSubmission $submission): IpcrSubmission
    {
        $user = $request->user();

        if ($user->role === UserRole::Administrator) {
            return IpcrSubmissionExportService::authorizeAdminExport($request, $submission);
        }

        if ($user->role === UserRole::Supervisor && $submission->supervisor_id === $user->id) {
            return IpcrSubmissionExportService::authorizeApprovedExport($request, $submission);
        }

        if ($user->role === UserRole::Employee && $submission->employee_id === $user->id) {
            return IpcrSubmissionExportService::authorizeEmployeeExport($request, $submission);
        }

        abort(403);
    }

    private function routePrefix(Request $request): string
    {
        return match ($request->user()->role) {
            UserRole::Administrator => 'admin',
            UserRole::Supervisor => 'supervisor',
            default => 'employee',
        };
    }

    private function documentUrl(Request $request, IpcrSubmission $submission): string
    {
        return route($this->routePrefix($request).'.submissions.document', $submission);
    }

    private function printUrl(Request $request, IpcrSubmission $submission): string
    {
        return route($this->routePrefix($request).'.submissions.print', $submission);
    }

    private function exportUrl(Request $request, IpcrSubmission $submission, string $format): string
    {
        return route($this->routePrefix($request).'.submissions.export', [
            'submission' => $submission,
            'format' => $format,
        ]);
    }

    private function updateUrl(Request $request, IpcrSubmission $submission): string
    {
        return route($this->routePrefix($request).'.submissions.commitment-statement', $submission);
    }

    private function backUrl(Request $request): string
    {
        return match ($request->user()->role) {
            UserRole::Administrator => route('admin.reports.ratings'),
            UserRole::Supervisor => route('dashboard'),
            default => route('dashboard'),
        };
    }
}
