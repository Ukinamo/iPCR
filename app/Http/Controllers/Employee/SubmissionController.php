<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\CommitmentWeightRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $user = $request->user();

        if ($user->supervisor_id === null) {
            throw ValidationException::withMessages([
                'evaluation_quarter' => 'You must be assigned to a supervisor before you can submit an IPCR package. Contact an administrator.',
            ]);
        }

        $commitments = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $data['evaluation_year'])
            ->where('evaluation_quarter', $data['evaluation_quarter'])
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
            ->get();

        if ($commitments->isEmpty()) {
            return back()->withErrors(['evaluation_quarter' => 'Add at least one commitment for this period before submitting.']);
        }

        $totals = CommitmentWeightRules::totalsForSubmissionBatch($commitments);
        $splitError = CommitmentWeightRules::submissionErrorIfInvalid($totals['core'], $totals['strategic']);
        if ($splitError !== null) {
            return back()->withErrors(['evaluation_quarter' => $splitError]);
        }

        $submission = IpcrSubmission::query()->firstOrNew([
            'employee_id' => $user->id,
            'evaluation_year' => $data['evaluation_year'],
            'evaluation_quarter' => $data['evaluation_quarter'],
        ]);

        if ($submission->exists && $submission->status === SubmissionStatus::Approved) {
            return back()->withErrors(['evaluation_quarter' => 'This period is already approved.']);
        }

        if ($submission->exists && $submission->status === SubmissionStatus::InReview) {
            return back()->withErrors(['evaluation_quarter' => 'This period is already with your supervisor.']);
        }

        $submission->supervisor_id = $user->supervisor_id;
        $submission->status = SubmissionStatus::InReview;
        $submission->submitted_at = now();
        $submission->save();

        foreach ($commitments as $c) {
            $c->update([
                'ipcr_submission_id' => $submission->id,
                'status' => CommitmentStatus::InReview,
            ]);
        }

        AuditLogger::log($user->id, 'ipcr.submitted', $submission, null, $request);

        return back()->with('status', 'Your IPCR package was sent for supervisor review.');
    }
}
