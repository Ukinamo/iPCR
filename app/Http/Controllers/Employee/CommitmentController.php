<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommitmentWeightRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommitmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'function_type' => ['required', 'in:core,strategic'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'period_label' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        $this->assertPeriodNotLocked($user, $data['evaluation_year'], $data['evaluation_quarter']);

        $this->assertWeightCapsAfterChange($user, $data, null);

        $commitment = Commitment::create([
            ...$data,
            'user_id' => $user->id,
            'status' => CommitmentStatus::Draft,
        ]);

        AuditLogger::log($user->id, 'commitment.created', $commitment, null, $request);

        return back();
    }

    public function update(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        if (! in_array($commitment->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true)) {
            return back()->withErrors(['title' => 'Only draft or returned commitments can be edited.']);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'function_type' => ['required', 'in:core,strategic'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'period_label' => ['required', 'string', 'max:32'],
        ]);

        $merged = [
            ...$data,
            'evaluation_year' => $commitment->evaluation_year,
            'evaluation_quarter' => $commitment->evaluation_quarter,
        ];

        $this->assertWeightCapsAfterChange($request->user(), $merged, $commitment->id);

        $commitment->update($data);

        AuditLogger::log($request->user()->id, 'commitment.updated', $commitment, null, $request);

        return back();
    }

    public function destroy(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        if (! in_array($commitment->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true)) {
            return back()->withErrors(['title' => 'Only draft or returned commitments can be deleted.']);
        }

        $commitment->delete();

        AuditLogger::log($request->user()->id, 'commitment.deleted', null, ['id' => $commitment->id], $request);

        return back();
    }

    private function authorizeCommitment(Request $request, Commitment $commitment): void
    {
        abort_if($commitment->user_id !== $request->user()->id, 403);
    }

    private function assertPeriodNotLocked(User $user, int $year, int $quarter): void
    {
        $submissionExists = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->where('status', CommitmentStatus::InReview)
            ->exists();

        if ($submissionExists) {
            throw ValidationException::withMessages([
                'title' => 'This quarter is currently with your supervisor for review. You cannot add or change commitments until the package is returned or approved.',
            ]);
        }
    }

    /**
     * @param  array{function_type: string, weight: float|int|string, evaluation_year: int, evaluation_quarter: int}  $data
     */
    private function assertWeightCapsAfterChange(User $user, array $data, ?int $excludeCommitmentId): void
    {
        $totals = CommitmentWeightRules::totalsForEditablePeriod(
            $user->id,
            (int) $data['evaluation_year'],
            (int) $data['evaluation_quarter'],
            $excludeCommitmentId,
        );

        $addCore = ($data['function_type'] === 'core') ? (float) $data['weight'] : 0.0;
        $addStrategic = ($data['function_type'] === 'strategic') ? (float) $data['weight'] : 0.0;

        $core = $totals['core'] + $addCore;
        $strategic = $totals['strategic'] + $addStrategic;

        $message = CommitmentWeightRules::assertCapsRespected($core, $strategic);
        if ($message !== null) {
            throw ValidationException::withMessages(['weight' => $message]);
        }
    }
}
