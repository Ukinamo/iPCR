<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Services\AuditLogger;
use App\Services\IpcrFormRatingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormAnswerController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'submission_id' => ['required', 'integer'],
            'commitments' => ['required', 'array', 'min:1'],
            'commitments.*.id' => ['required', 'integer'],
            'commitments.*.rating_q1_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q1_actual' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q2_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q2_actual' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q3_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q3_actual' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q4_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q4_actual' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_quality' => ['nullable', 'integer', 'min:0', 'max:5'],
            'commitments.*.rating_efficiency' => ['nullable', 'integer', 'min:0', 'max:5'],
            'commitments.*.rating_timeliness' => ['nullable', 'integer', 'min:0', 'max:5'],
        ]);

        $user = $request->user();

        $submission = \App\Models\IpcrSubmission::query()
            ->where('id', $data['submission_id'])
            ->where('employee_id', $user->id)
            ->whereIn('status', [\App\Enums\SubmissionStatus::Pending, \App\Enums\SubmissionStatus::Returned])
            ->first();

        if ($submission === null) {
            throw ValidationException::withMessages([
                'commitments' => 'This package cannot be updated right now.',
            ]);
        }

        $commitments = Commitment::query()
            ->where('user_id', $user->id)
            ->where('ipcr_submission_id', $submission->id)
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
            ->get()
            ->keyBy('id');

        if ($commitments->isEmpty()) {
            throw ValidationException::withMessages([
                'commitments' => 'No assigned IPCR form is available to update for this period.',
            ]);
        }

        foreach ($data['commitments'] as $index => $row) {
            if (! $commitments->has((int) $row['id'])) {
                throw ValidationException::withMessages([
                    "commitments.{$index}.id" => 'Invalid row for your assigned IPCR form.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $user, $data, $commitments, $submission) {
            $quarters = \App\Support\IpcrIncludedQuarters::existingOrDefault($submission->included_quarters);
            foreach ($data['commitments'] as $row) {
                $commitment = $commitments->get((int) $row['id']);
                IpcrFormRatingCalculator::applyRowRatings($commitment, $row, true, $quarters);
            }

            AuditLogger::log($user->id, 'ipcr.answers.updated', $commitments->first(), null, $request);
        });

        return back()->with('status', 'Accomplishments saved.');
    }
}
