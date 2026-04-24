<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\IpcrFormRatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubmissionReviewController extends Controller
{
    public function update(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $supervisor = $request->user();

        abort_unless($submission->supervisor_id === $supervisor->id, 403);
        abort_unless($submission->status === SubmissionStatus::InReview, 422);

        $base = $request->validate([
            'action' => ['required', 'in:approve,return'],
            'supervisor_feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($base['action'] === 'approve') {
            $data = array_merge($base, $request->validate([
                'commitments' => ['required', 'array', 'min:1'],
                'commitments.*.id' => ['required', 'integer'],
                'commitments.*.rating_efficiency' => ['required', 'integer', 'min:1', 'max:5'],
                'commitments.*.rating_timeliness' => ['required', 'integer', 'min:1', 'max:5'],
                'commitments.*.rating_q3_target' => ['required', 'numeric', 'min:0'],
                'commitments.*.rating_q3_actual' => ['required', 'numeric', 'min:0'],
                'commitments.*.rating_q4_target' => ['required', 'numeric', 'min:0'],
                'commitments.*.rating_q4_actual' => ['required', 'numeric', 'min:0'],
                'commitments.*.remarks' => ['nullable', 'string', 'max:255'],
            ]));
        } else {
            $data = $base;
        }

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
            $submission->load('commitments');

            $rows = $data['commitments'] ?? [];
            if ($submission->commitments->isEmpty()) {
                return back()->withErrors(['commitments' => 'This submission has no commitments to rate.']);
            }

            $expectedIds = $submission->commitments->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $incomingIds = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($expectedIds !== $incomingIds) {
                return back()->withErrors([
                    'commitments' => 'Rate every commitment in this package exactly once (add missing rows or remove extras).',
                ]);
            }

            foreach ($rows as $row) {
                $totals = IpcrFormRatingCalculator::totalsFromQ3Q4(
                    (float) $row['rating_q3_target'],
                    (float) $row['rating_q3_actual'],
                    (float) $row['rating_q4_target'],
                    (float) $row['rating_q4_actual'],
                );

                if ($totals['target_total'] <= 0) {
                    return back()->withErrors([
                        'commitments' => 'Q3 + Q4 target totals must be greater than zero for each commitment.',
                    ]);
                }
            }

            $sumWeighted = 0.0;

            foreach ($rows as $row) {
                $commitment = $submission->commitments->firstWhere('id', (int) $row['id']);

                $totals = IpcrFormRatingCalculator::totalsFromQ3Q4(
                    (float) $row['rating_q3_target'],
                    (float) $row['rating_q3_actual'],
                    (float) $row['rating_q4_target'],
                    (float) $row['rating_q4_actual'],
                );
                $ratio = $totals['percent'];

                $efficiency = (int) $row['rating_efficiency'];
                $timeliness = (int) $row['rating_timeliness'];

                $scored = IpcrFormRatingCalculator::scoreRow(
                    $efficiency,
                    $timeliness,
                    (float) $commitment->weight,
                    $ratio,
                );

                $commitment->update([
                    'rating_q3_target' => $row['rating_q3_target'],
                    'rating_q3_actual' => $row['rating_q3_actual'],
                    'rating_q4_target' => $row['rating_q4_target'],
                    'rating_q4_actual' => $row['rating_q4_actual'],
                    'rating_target_total' => $totals['target_total'],
                    'rating_actual_total' => $totals['actual_total'],
                    'rating_percent' => $totals['percent'],
                    'rating_quality' => $scored['quality'],
                    'rating_efficiency' => $efficiency,
                    'rating_timeliness' => $timeliness,
                    'rating_average' => $scored['average'],
                    'rating_weighted' => $scored['weighted'],
                    'remarks' => $row['remarks'] ?? null,
                ]);

                $sumWeighted += $scored['weighted'];
            }

            $submission->update([
                'quality' => null,
                'efficiency' => null,
                'timeliness' => null,
                'overall_rating' => round($sumWeighted, 2),
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

            $submission->commitments()->update([
                'status' => CommitmentStatus::Returned,
                'rating_actual_total' => null,
                'rating_target_total' => null,
                'rating_q3_target' => null,
                'rating_q3_actual' => null,
                'rating_q4_target' => null,
                'rating_q4_actual' => null,
                'rating_percent' => null,
                'rating_quality' => null,
                'rating_efficiency' => null,
                'rating_timeliness' => null,
                'rating_average' => null,
                'rating_weighted' => null,
                'remarks' => null,
            ]);
        }

        AuditLogger::log($supervisor->id, 'ipcr.reviewed', $submission, ['action' => $data['action']], $request);

        return back()->with('status', 'Review saved.');
    }
}
