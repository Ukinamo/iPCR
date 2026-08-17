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
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'commitments' => ['required', 'array', 'min:1'],
            'commitments.*.id' => ['required', 'integer'],
            'commitments.*.rating_q3_target' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q3_actual' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q4_target' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q4_actual' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        $commitments = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $data['evaluation_year'])
            ->where('evaluation_quarter', $data['evaluation_quarter'])
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

        DB::transaction(function () use ($request, $user, $data, $commitments) {
            foreach ($data['commitments'] as $row) {
                $commitment = $commitments->get((int) $row['id']);
                IpcrFormRatingCalculator::applyRowRatings($commitment, $row, true);
            }

            AuditLogger::log($user->id, 'ipcr.answers.updated', $commitments->first(), null, $request);
        });

        return back()->with('status', 'Accomplishments saved.');
    }
}
