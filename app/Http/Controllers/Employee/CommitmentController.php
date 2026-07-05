<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Accomplishment;
use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommitmentPeriodGuard;
use App\Services\CommitmentWeightRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->has('entries')) {
            return $this->storeBatch($request);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'function_type' => ['required', 'in:core,strategic'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'annual_office_target' => ['nullable', 'string', 'max:255'],
            'individual_annual_targets' => ['nullable', 'string', 'max:255'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'period_label' => ['required', 'string', 'max:32'],
            'evidence_title' => ['nullable', 'string', 'max:255'],
            'evidence_description' => ['nullable', 'string', 'max:8000'],
            'evidence_file' => ['nullable', 'file', 'max:12288', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $user = $request->user();

        $this->assertPeriodNotLocked($user, $data['evaluation_year'], $data['evaluation_quarter']);

        $this->assertWeightCapsAfterChange($user, $data, null);

        $wantsEvidence = $request->hasFile('evidence_file')
            || filled($data['evidence_title'] ?? null)
            || filled($data['evidence_description'] ?? null);

        $batchId = (string) Str::uuid();

        DB::transaction(function () use ($request, $user, $data, $wantsEvidence, $batchId) {
            $commitment = Commitment::create([
                'user_id' => $user->id,
                'batch_id' => $batchId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'function_type' => $data['function_type'],
                'weight' => $data['weight'],
                'annual_office_target' => $data['annual_office_target'] ?? null,
                'individual_annual_targets' => $data['individual_annual_targets'] ?? null,
                'progress' => 0,
                'evaluation_year' => $data['evaluation_year'],
                'evaluation_quarter' => $data['evaluation_quarter'],
                'period_label' => $data['period_label'],
                'status' => CommitmentStatus::Draft,
            ]);

            AuditLogger::log($user->id, 'commitment.created', $commitment, null, $request);

            if ($wantsEvidence) {
                $path = null;
                $original = null;
                $mime = null;
                $size = null;

                if ($request->hasFile('evidence_file')) {
                    $file = $request->file('evidence_file');
                    $path = $file->store('commitment-evidence/'.$user->id, 'public');
                    $original = $file->getClientOriginalName();
                    $mime = $file->getClientMimeType() ?: $file->getMimeType();
                    $size = $file->getSize();
                }

                $evidenceTitle = trim((string) ($data['evidence_title'] ?? ''));
                if ($evidenceTitle === '') {
                    $evidenceTitle = $data['title'];
                }

                $accomplishment = Accomplishment::create([
                    'user_id' => $user->id,
                    'commitment_id' => $commitment->id,
                    'title' => $evidenceTitle,
                    'description' => $data['evidence_description'] ?? null,
                    'file_path' => $path,
                    'original_filename' => $original,
                    'mime_type' => $mime,
                    'file_size' => $size,
                ]);

                AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);
            }
        });

        return back()->with('status', $wantsEvidence ? 'Commitment and evidence saved.' : 'Commitment saved.');
    }

    private function storeBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'period_label' => ['required', 'string', 'max:32'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.function_type' => ['required', 'in:core,strategic'],
            'entries.*.title' => ['required', 'string', 'max:255'],
            'entries.*.description' => ['nullable', 'string', 'max:8000'],
            'entries.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.annual_office_target' => ['nullable', 'string', 'max:255'],
            'entries.*.individual_annual_targets' => ['nullable', 'string', 'max:255'],
            'evidence_title' => ['nullable', 'string', 'max:255'],
            'evidence_description' => ['nullable', 'string', 'max:8000'],
            'evidence_files' => ['nullable', 'array', 'max:3'],
            'evidence_files.*' => ['file', 'max:12288', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $user = $request->user();
        $year = (int) $data['evaluation_year'];
        $quarter = (int) $data['evaluation_quarter'];

        $this->assertPeriodNotLocked($user, $year, $quarter, 'entries');

        $totals = CommitmentWeightRules::totalsForEditablePeriod($user->id, $year, $quarter);
        $core = $totals['core'];
        $strategic = $totals['strategic'];

        foreach ($data['entries'] as $entry) {
            if ($entry['function_type'] === 'core') {
                $core += (float) $entry['weight'];
            } else {
                $strategic += (float) $entry['weight'];
            }

            $message = CommitmentWeightRules::assertCapsRespected($core, $strategic);
            if ($message !== null) {
                throw ValidationException::withMessages(['entries' => $message]);
            }
        }

        $batchId = (string) Str::uuid();

        DB::transaction(function () use ($request, $user, $data, $batchId) {
            $firstCommitment = null;

            foreach ($data['entries'] as $entry) {
                $commitment = Commitment::create([
                    'user_id' => $user->id,
                    'batch_id' => $batchId,
                    'title' => $entry['title'],
                    'description' => $entry['description'] ?? null,
                    'function_type' => $entry['function_type'],
                    'weight' => $entry['weight'],
                    'annual_office_target' => $entry['annual_office_target'] ?? null,
                    'individual_annual_targets' => $entry['individual_annual_targets'] ?? null,
                    'progress' => 0,
                    'evaluation_year' => $data['evaluation_year'],
                    'evaluation_quarter' => $data['evaluation_quarter'],
                    'period_label' => $data['period_label'],
                    'status' => CommitmentStatus::Draft,
                ]);

                if ($firstCommitment === null) {
                    $firstCommitment = $commitment;
                }

                AuditLogger::log($user->id, 'commitment.created', $commitment, null, $request);
            }

            if ($firstCommitment === null) {
                return;
            }

            $files = $request->file('evidence_files') ?? [];
            if (! is_array($files)) {
                $files = [$files];
            }
            $files = array_values(array_filter($files));

            $sharedTitle = trim((string) ($data['evidence_title'] ?? ''));
            $sharedDescription = $data['evidence_description'] ?? null;

            $wantsEvidence = ! empty($files)
                || $sharedTitle !== ''
                || filled($sharedDescription);

            if (! $wantsEvidence) {
                return;
            }

            if ($sharedTitle === '') {
                $sharedTitle = $firstCommitment->title;
            }

            if (empty($files)) {
                $accomplishment = Accomplishment::create([
                    'user_id' => $user->id,
                    'commitment_id' => $firstCommitment->id,
                    'title' => $sharedTitle,
                    'description' => $sharedDescription,
                ]);
                AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);

                return;
            }

            foreach ($files as $file) {
                $path = $file->store('commitment-evidence/'.$user->id, 'public');

                $accomplishment = Accomplishment::create([
                    'user_id' => $user->id,
                    'commitment_id' => $firstCommitment->id,
                    'title' => $sharedTitle,
                    'description' => $sharedDescription,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                AuditLogger::log($user->id, 'accomplishment.created', $accomplishment, null, $request);
            }
        });

        return back()->with('status', 'Commitments saved.');
    }

    public function show(Request $request, Commitment $commitment): Response
    {
        $this->authorizeCommitment($request, $commitment);

        $siblings = $this->siblingsFor($commitment);

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

        $submission = IpcrSubmission::query()
            ->where('employee_id', $commitment->user_id)
            ->where('evaluation_year', $commitment->evaluation_year)
            ->where('evaluation_quarter', $commitment->evaluation_quarter)
            ->first();

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
                'status' => $submission->status->value,
                'supervisor_feedback' => $submission->supervisor_feedback,
            ] : null,
        ]);
    }

    public function updateBatch(Request $request, Commitment $commitment): RedirectResponse
    {
        $this->authorizeCommitment($request, $commitment);

        $siblings = $this->siblingsFor($commitment);

        if ($siblings->contains(fn ($c) => ! in_array($c->status, [CommitmentStatus::Draft, CommitmentStatus::Returned], true))) {
            return back()->withErrors(['entries' => 'Only draft or returned commitment packages can be edited.']);
        }

        $data = $request->validate([
            'period_label' => ['required', 'string', 'max:32'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.function_type' => ['required', 'in:core,strategic'],
            'entries.*.title' => ['required', 'string', 'max:255'],
            'entries.*.description' => ['nullable', 'string', 'max:8000'],
            'entries.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.annual_office_target' => ['nullable', 'string', 'max:255'],
            'entries.*.individual_annual_targets' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $year = (int) $commitment->evaluation_year;
        $quarter = (int) $commitment->evaluation_quarter;
        $siblingIds = $siblings->pluck('id')->all();

        foreach ($data['entries'] as $index => $entry) {
            if (! empty($entry['id']) && ! in_array((int) $entry['id'], $siblingIds, true)) {
                throw ValidationException::withMessages([
                    "entries.{$index}.id" => 'Invalid commitment row for this package.',
                ]);
            }
        }

        $otherTotals = CommitmentWeightRules::totalsExcludingCommitmentIds($user->id, $year, $quarter, $siblingIds);
        $core = $otherTotals['core'];
        $strategic = $otherTotals['strategic'];

        foreach ($data['entries'] as $entry) {
            if ($entry['function_type'] === 'core') {
                $core += (float) $entry['weight'];
            } else {
                $strategic += (float) $entry['weight'];
            }

            $message = CommitmentWeightRules::assertCapsRespected($core, $strategic);
            if ($message !== null) {
                throw ValidationException::withMessages(['entries' => $message]);
            }
        }

        $incomingIds = collect($data['entries'])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $status = $siblings->contains(fn ($c) => $c->status === CommitmentStatus::Returned)
            ? CommitmentStatus::Returned
            : CommitmentStatus::Draft;

        DB::transaction(function () use ($request, $user, $commitment, $siblings, $data, $incomingIds, $status) {
            foreach ($data['entries'] as $entry) {
                $payload = [
                    'title' => $entry['title'],
                    'description' => $entry['description'] ?? null,
                    'function_type' => $entry['function_type'],
                    'weight' => $entry['weight'],
                    'annual_office_target' => $entry['annual_office_target'] ?? null,
                    'individual_annual_targets' => $entry['individual_annual_targets'] ?? null,
                    'period_label' => $data['period_label'],
                    'status' => $status,
                ];

                if (! empty($entry['id'])) {
                    $row = $siblings->firstWhere('id', (int) $entry['id']);
                    if ($row) {
                        $row->update($payload);
                        AuditLogger::log($user->id, 'commitment.updated', $row, null, $request);

                        continue;
                    }
                }

                $created = Commitment::create([
                    ...$payload,
                    'user_id' => $user->id,
                    'batch_id' => $commitment->batch_id,
                    'ipcr_submission_id' => $commitment->ipcr_submission_id,
                    'evaluation_year' => $commitment->evaluation_year,
                    'evaluation_quarter' => $commitment->evaluation_quarter,
                    'progress' => 0,
                ]);

                AuditLogger::log($user->id, 'commitment.created', $created, null, $request);
            }

            $siblings->each(function (Commitment $row) use ($incomingIds, $request, $user) {
                if (! in_array($row->id, $incomingIds, true)) {
                    $row->delete();
                    AuditLogger::log($user->id, 'commitment.deleted', null, ['id' => $row->id], $request);
                }
            });
        });

        return back()->with('status', 'Commitments updated.');
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
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'annual_office_target' => ['nullable', 'string', 'max:255'],
            'individual_annual_targets' => ['nullable', 'string', 'max:255'],
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

        return $query->orderBy('function_type')->orderBy('id')->get();
    }

    private function assertPeriodNotLocked(User $user, int $year, int $quarter, string $errorKey = 'title'): void
    {
        CommitmentPeriodGuard::assertCanAddCommitments($user, $year, $quarter, $errorKey);
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
