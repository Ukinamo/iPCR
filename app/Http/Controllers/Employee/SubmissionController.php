<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\CommitmentWeightRules;
use App\Services\SupervisorTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        if ($user->supervisor_id === null) {
            throw ValidationException::withMessages([
                'submission_id' => 'You must be assigned to a supervisor before you can submit an IPCR package. Contact an administrator.',
            ]);
        }

        $submission = IpcrSubmission::query()
            ->where('id', $data['submission_id'])
            ->where('employee_id', $user->id)
            ->firstOrFail();

        if (! in_array($submission->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true)) {
            throw ValidationException::withMessages([
                'submission_id' => 'This package is already submitted or approved.',
            ]);
        }

        $commitments = $submission->commitments()
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
            ->get();

        if ($commitments->isEmpty()) {
            return back()->withErrors(['submission_id' => 'This package has no form rows to submit.']);
        }

        $totals = CommitmentWeightRules::totalsForSubmissionBatch($commitments);
        $splitError = CommitmentWeightRules::submissionErrorIfInvalid($totals['core'], $totals['strategic']);
        if ($splitError !== null) {
            return back()->withErrors(['submission_id' => $splitError]);
        }

        $submission->supervisor_id = $user->supervisor_id;
        $submission->status = SubmissionStatus::InReview;
        $submission->submitted_at = now();
        $submission->save();

        foreach ($commitments as $c) {
            $c->update([
                'status' => CommitmentStatus::InReview,
            ]);
        }

        AuditLogger::log($user->id, 'ipcr.submitted', $submission, null, $request);

        app(SupervisorTransferService::class)->notifySubmissionSubmitted($submission->fresh(['employee', 'supervisor']));

        return redirect()
            ->route('dashboard')
            ->with('status', 'Your IPCR package was sent for administrator review.');
    }

    public function cancel(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        abort_unless($submission->employee_id === $request->user()->id, 403);

        if ($submission->status !== SubmissionStatus::InReview) {
            throw ValidationException::withMessages([
                'submission_id' => 'Only a submitted package can be cancelled.',
            ]);
        }

        $submission->status = SubmissionStatus::Pending;
        $submission->submitted_at = null;
        $submission->save();

        $submission->commitments()
            ->where('status', CommitmentStatus::InReview)
            ->update(['status' => CommitmentStatus::Draft]);

        AuditLogger::log($request->user()->id, 'ipcr.submission.cancelled', $submission, null, $request);

        return back()->with('status', 'Submission cancelled. You can edit and submit again.');
    }
}
