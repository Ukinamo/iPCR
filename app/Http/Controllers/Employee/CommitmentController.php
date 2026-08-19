<?php

namespace App\Http\Controllers\Employee;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Services\CommitmentWeightRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    public function store(): RedirectResponse
    {
        return $this->denyStructureEdit();
    }

    public function show(Request $request, Commitment $commitment): Response|RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        $siblings = $this->siblingsFor($commitment);

        $submission = IpcrSubmission::query()
            ->where('employee_id', $commitment->user_id)
            ->where('evaluation_year', $commitment->evaluation_year)
            ->where('evaluation_quarter', $commitment->evaluation_quarter)
            ->first();

        if ($submission?->status === SubmissionStatus::Approved) {
            return redirect()->route('dashboard');
        }

        $totalWeight = (float) $siblings->sum('weight');
        $totalEvidence = (int) $siblings->sum(fn ($c) => $c->accomplishments->count());

        $functionTitles = $siblings
            ->map(fn ($c) => ['function_type' => $c->function_type, 'title' => $c->title])
            ->unique(fn ($r) => $r['function_type'].'|'.$r['title'])
            ->values();

        $weightSummary = CommitmentWeightRules::summaryForEmployee(
            $commitment->user_id,
            (int) $commitment->evaluation_year,
            (int) $commitment->evaluation_quarter,
        );

        return Inertia::render('Employee/CommitmentShow', [
            'group' => [
                'batch_id' => $commitment->batch_id,
                'period_label' => $commitment->period_label,
                'evaluation_year' => $commitment->evaluation_year,
                'evaluation_quarter' => $commitment->evaluation_quarter,
                'total_weight' => round($totalWeight, 2),
                'total_indicators' => $siblings->count(),
                'total_evidence' => $totalEvidence,
                'total_functions' => $functionTitles->count(),
                'functions' => $functionTitles,
                'created_at' => optional($siblings->first()?->created_at)->toIso8601String(),
            ],
            'commitments' => $siblings,
            'weightSummary' => $weightSummary,
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status->value,
                'supervisor_feedback' => $submission->supervisor_feedback,
            ] : null,
        ]);
    }

    public function updateBatch(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        return $this->denyStructureEdit();
    }

    public function update(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        return $this->denyStructureEdit();
    }

    public function destroy(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        return $this->denyStructureEdit();
    }

    private function denyStructureEdit(): RedirectResponse
    {
        throw ValidationException::withMessages([
            'entries' => 'The administrator creates and assigns the IPCR form. You can fill accomplishments on the assigned form; rating, average, and remarks compute automatically.',
        ]);
    }

    private function authorizeCommitment(Request $request, Commitment $commitment): void
    {
        abort_if($commitment->user_id !== $request->user()->id, 403);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Commitment>
     */
    private function siblingsFor(Commitment $commitment)
    {
        $query = Commitment::query()
            ->where('user_id', $commitment->user_id)
            ->with(['accomplishments' => fn ($q) => $q->orderByDesc('created_at')]);

        if (! empty($commitment->batch_id)) {
            $query->where('batch_id', $commitment->batch_id);
        } elseif ($commitment->ipcr_submission_id) {
            $query->where('ipcr_submission_id', $commitment->ipcr_submission_id);
        } else {
            $query
                ->whereNull('batch_id')
                ->where('evaluation_year', $commitment->evaluation_year)
                ->where('evaluation_quarter', $commitment->evaluation_quarter)
                ->where('function_type', $commitment->function_type)
                ->where('title', $commitment->title);
        }

        return $query->inFormOrder()->get();
    }
}
