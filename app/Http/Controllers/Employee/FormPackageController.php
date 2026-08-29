<?php

namespace App\Http\Controllers\Employee;

use App\Enums\CommitmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\IpcrFormTemplate;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\CommitmentWeightRules;
use App\Services\IpcrFormTemplateProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormPackageController extends Controller
{
    public function fromTemplate(Request $request, IpcrFormTemplateProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:ipcr_form_templates,id'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
        ]);

        $user = $request->user();
        $template = IpcrFormTemplate::query()->with('items')->findOrFail($data['template_id']);

        if ($template->items->isEmpty()) {
            throw ValidationException::withMessages([
                'template_id' => 'This form has no rows to copy.',
            ]);
        }

        $submission = $provisioner->copyTemplateForEmployee(
            $template,
            $user,
            (int) $data['evaluation_year'],
            (int) $data['evaluation_quarter'],
        );

        AuditLogger::log($user->id, 'ipcr.package.copied', $submission, [
            'template_id' => $template->id,
        ], $request);

        return redirect()
            ->route('employee.packages.edit', $submission)
            ->with('status', 'Form copied. You can edit your copy without changing the original.');
    }

    public function store(Request $request, IpcrFormTemplateProvisioner $provisioner): RedirectResponse
    {
        $data = $this->validatedEntries($request);
        $this->assertWeights($data['entries']);

        $submission = $provisioner->createBlankPackage(
            $request->user(),
            (int) $data['evaluation_year'],
            (int) $data['evaluation_quarter'],
            (string) ($data['title'] ?? ''),
            $data['entries'],
        );

        AuditLogger::log($request->user()->id, 'ipcr.package.created', $submission, null, $request);

        return redirect()
            ->route('employee.packages.edit', $submission)
            ->with('status', 'Your IPCR form was created.');
    }

    public function edit(Request $request, IpcrSubmission $submission): Response
    {
        $this->authorizePackage($request, $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true), 422);

        $submission->load('commitments');
        $totals = CommitmentWeightRules::totalsFromRows($submission->commitments);

        return Inertia::render('Employee/PackageEdit', [
            'package' => $submission,
            'weightSummary' => [
                ...$totals,
                'core_remaining' => round(max(0, CommitmentWeightRules::CORE_CAP - $totals['core']), 2),
                'strategic_remaining' => round(max(0, CommitmentWeightRules::STRATEGIC_CAP - $totals['strategic']), 2),
                'core_cap' => CommitmentWeightRules::CORE_CAP,
                'strategic_cap' => CommitmentWeightRules::STRATEGIC_CAP,
                'meets_submit_requirement' => CommitmentWeightRules::meetsSpmsSplit($totals['core'], $totals['strategic']),
            ],
        ]);
    }

    public function update(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $this->authorizePackage($request, $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true), 422);

        $data = $this->validatedEntries($request);
        $this->assertWeights($data['entries']);

        $openId = DB::transaction(function () use ($request, $submission, $data) {
            $submission->update([
                'title' => $data['title'] ?: ($submission->title ?: 'My IPCR form'),
            ]);

            $existing = $submission->commitments()->get()->keyBy('id');
            $keptIds = [];
            $periodLabel = $submission->commitments()->value('period_label')
                ?: ('Q'.$submission->evaluation_quarter.' '.$submission->evaluation_year);

            foreach (array_values($data['entries']) as $index => $entry) {
                $payload = [
                    'title' => filled($entry['title'] ?? null) ? $entry['title'] : null,
                    'description' => $entry['description'] ?? null,
                    'function_type' => $entry['function_type'],
                    'sort_order' => (int) ($entry['sort_order'] ?? $index),
                    'function_group' => (int) ($entry['function_group'] ?? $index),
                    'weight' => isset($entry['weight']) && $entry['weight'] !== '' && $entry['weight'] !== null
                        ? round((float) $entry['weight'], 2)
                        : null,
                    'annual_office_target' => $entry['annual_office_target'] ?? null,
                    'individual_annual_targets' => $entry['individual_annual_targets'] ?? null,
                    'ipcr_form_template_item_id' => null,
                    'status' => CommitmentStatus::Draft,
                ];

                $id = isset($entry['id']) ? (int) $entry['id'] : 0;
                $row = $id > 0 ? $existing->get($id) : null;

                if ($row) {
                    $row->update($payload);
                    $keptIds[] = $row->id;
                } else {
                    $created = $submission->commitments()->create([
                        ...$payload,
                        'user_id' => $submission->employee_id,
                        'batch_id' => $submission->batch_id,
                        'ipcr_form_template_id' => $submission->ipcr_form_template_id,
                        'evaluation_year' => $submission->evaluation_year,
                        'evaluation_quarter' => $submission->evaluation_quarter,
                        'period_label' => $periodLabel,
                        'progress' => 0,
                    ]);
                    $keptIds[] = $created->id;
                }
            }

            $submission->commitments()
                ->whereNotIn('id', $keptIds)
                ->get()
                ->each(function ($commitment) {
                    $commitment->accomplishments()->delete();
                    $commitment->delete();
                });

            AuditLogger::log($request->user()->id, 'ipcr.package.updated', $submission, null, $request);

            return $submission->commitments()->inFormOrder()->value('id');
        });

        if (! $openId) {
            return back()->withErrors(['entries' => 'Add at least one row before rating.']);
        }

        return redirect()
            ->route('employee.commitments.show', $openId)
            ->with('status', 'Your copy was saved. Fill ratings, then submit.');
    }

    public function destroy(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $this->authorizePackage($request, $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::Pending, SubmissionStatus::Returned], true), 422);

        DB::transaction(function () use ($request, $submission) {
            $submission->commitments()->get()->each(function ($commitment) {
                $commitment->accomplishments()->delete();
                $commitment->delete();
            });

            AuditLogger::log($request->user()->id, 'ipcr.package.deleted', null, [
                'id' => $submission->id,
                'title' => $submission->title,
            ], $request);

            $submission->delete();
        });

        return redirect()
            ->route('dashboard')
            ->with('status', 'The IPCR form was deleted.');
    }

    private function authorizePackage(Request $request, IpcrSubmission $submission): void
    {
        abort_unless($submission->employee_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEntries(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'evaluation_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.function_type' => ['required', 'in:core,strategic'],
            'entries.*.function_group' => ['nullable', 'integer', 'min:0'],
            'entries.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'entries.*.title' => ['nullable', 'string', 'max:255'],
            'entries.*.description' => ['nullable', 'string', 'max:8000'],
            'entries.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'entries.*.annual_office_target' => ['nullable', 'string', 'max:255'],
            'entries.*.individual_annual_targets' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function assertWeights(array $entries): void
    {
        $core = 0.0;
        $strategic = 0.0;
        foreach ($entries as $entry) {
            $weight = (float) ($entry['weight'] ?? 0);
            if (($entry['function_type'] ?? '') === 'core') {
                $core += $weight;
            } else {
                $strategic += $weight;
            }
        }

        $message = CommitmentWeightRules::assertCapsRespected($core, $strategic);
        if ($message !== null) {
            throw ValidationException::withMessages(['entries' => $message]);
        }
    }
}
