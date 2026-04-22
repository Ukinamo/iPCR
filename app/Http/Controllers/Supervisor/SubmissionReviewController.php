<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\SpmsRatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    public function update(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $supervisor = $request->user();

        abort_unless($submission->supervisor_id === $supervisor->id, 403);
        abort_unless($submission->status === SubmissionStatus::InReview, 422);

        $data = $request->validate([
            'action' => ['required', 'in:approve,return'],
            'quality' => ['required_if:action,approve', 'nullable', 'integer', 'min:1', 'max:5'],
            'efficiency' => ['required_if:action,approve', 'nullable', 'integer', 'min:1', 'max:5'],
            'timeliness' => ['required_if:action,approve', 'nullable', 'integer', 'min:1', 'max:5'],
            'supervisor_feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['action'] === 'return') {
            $feedback = trim((string) ($data['supervisor_feedback'] ?? ''));
            if (strlen($feedback) < 20) {
                return back()->withErrors([
                    'supervisor_feedback' => 'Returning for revision requires clear guidance: please write at least 20 characters for the employee.',
                ]);
            }
            $data['supervisor_feedback'] = $feedback;
        }

        if ($data['action'] === 'approve') {
            $overall = SpmsRatingCalculator::overall(
                (int) $data['quality'],
                (int) $data['efficiency'],
                (int) $data['timeliness'],
            );

            $submission->update([
                'quality' => $data['quality'],
                'efficiency' => $data['efficiency'],
                'timeliness' => $data['timeliness'],
                'overall_rating' => $overall,
                'supervisor_feedback' => $data['supervisor_feedback'] ?? null,
                'status' => SubmissionStatus::Approved,
                'reviewed_at' => now(),
            ]);

            $submission->commitments()->update(['status' => CommitmentStatus::Approved]);
        } else {
            $submission->update([
                'status' => SubmissionStatus::Returned,
                'supervisor_feedback' => $data['supervisor_feedback'],
                'reviewed_at' => now(),
                'quality' => null,
                'efficiency' => null,
                'timeliness' => null,
                'overall_rating' => null,
            ]);

            $submission->commitments()->update(['status' => CommitmentStatus::Returned]);
        }

        AuditLogger::log($supervisor->id, 'ipcr.reviewed', $submission, ['action' => $data['action']], $request);

        return back()->with('status', 'Review saved.');
    }
}
