<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramEvaluationForm;
use App\Models\StoMonitoringForm;
use App\Services\RegisterReportReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisterReportReviewController extends Controller
{
    public function showProgram(Request $request, ProgramEvaluationForm $form): Response
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $form->load(['entries', 'supervisor:id,name,email']);

        return Inertia::render('Supervisor/ProgramEvaluations/Edit', [
            'formRecord' => $form,
            'periodYear' => (int) $form->evaluation_year,
            'viewer' => 'admin',
        ]);
    }

    public function showSto(Request $request, StoMonitoringForm $stoMonitoringForm): Response
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $stoMonitoringForm->load(['entries', 'supervisor:id,name,email']);

        return Inertia::render('Supervisor/StoMonitoring/Edit', [
            'formRecord' => $stoMonitoringForm,
            'reportType' => $stoMonitoringForm->report_type->value,
            'periodYear' => (int) $stoMonitoringForm->evaluation_year,
            'viewer' => 'admin',
        ]);
    }

    public function updateProgram(Request $request, ProgramEvaluationForm $form, RegisterReportReviewService $review): RedirectResponse
    {
        $this->reviewForm($request, $form, $review);

        return back()->with('status', $request->input('action') === 'approve'
            ? 'Programs evaluated report approved.'
            : 'Programs evaluated report returned to the supervisor.');
    }

    public function updateSto(Request $request, StoMonitoringForm $stoMonitoringForm, RegisterReportReviewService $review): RedirectResponse
    {
        $this->reviewForm($request, $stoMonitoringForm, $review);

        return back()->with('status', $request->input('action') === 'approve'
            ? 'STO monitoring report approved.'
            : 'STO monitoring report returned to the supervisor.');
    }

    private function reviewForm(Request $request, ProgramEvaluationForm|StoMonitoringForm $form, RegisterReportReviewService $review): void
    {
        abort_unless($request->user()->isAdministrator(), 403);

        $data = $request->validate([
            'action' => ['required', 'in:approve,return'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->review($form, $request->user(), $data['action'], $data['review_notes'] ?? null, $request);
    }
}
