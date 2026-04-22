<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommitmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'function_type' => ['required', 'in:core,strategic'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'period_label' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        $submissionExists = Commitment::query()
            ->where('user_id', $user->id)
            ->where('evaluation_year', $data['evaluation_year'])
            ->where('evaluation_quarter', $data['evaluation_quarter'])
            ->where('status', CommitmentStatus::InReview)
            ->exists();

        if ($submissionExists) {
            return back()->withErrors(['title' => 'This period is under review. You cannot add commitments until it is returned or finalized.']);
        }

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
            'function_type' => ['required', 'in:core,strategic'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'period_label' => ['required', 'string', 'max:32'],
        ]);

        $commitment->update($data);

        AuditLogger::log($request->user()->id, 'commitment.updated', $commitment, null, $request);

        return back();
    }

    public function destroy(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        if ($commitment->status !== CommitmentStatus::Draft) {
            return back()->withErrors(['title' => 'Only draft commitments can be deleted.']);
        }

        $commitment->delete();

        AuditLogger::log($request->user()->id, 'commitment.deleted', null, ['id' => $commitment->id], $request);

        return back();
    }

    private function authorizeCommitment(Request $request, Commitment $commitment): void
    {
        abort_if($commitment->user_id !== $request->user()->id, 403);
    }
}
