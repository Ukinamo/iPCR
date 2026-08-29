<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormTemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Commitment;
use App\Models\IpcrFormTemplate;
use App\Models\IpcrSubmission;
use App\Services\AuditLogger;
use App\Services\CommitmentWeightRules;
use App\Support\IpcrIncludedQuarters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormTemplateController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard', ['tab' => 'forms']);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('dashboard', ['tab' => 'forms']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $quarters = IpcrIncludedQuarters::normalize($data['included_quarters'] ?? []);
        if ($quarters === []) {
            throw ValidationException::withMessages([
                'included_quarters' => 'Select at least one quarter to include in accomplishments.',
            ]);
        }
        $data['included_quarters'] = $quarters;
        $data['evaluation_quarter'] = IpcrIncludedQuarters::primaryQuarter($quarters);
        $data['period_label'] = IpcrIncludedQuarters::periodLabel((int) $data['evaluation_year'], $quarters);
        $this->assertWeights($data['entries'], false);

        $template = DB::transaction(function () use ($request, $data) {
            $template = IpcrFormTemplate::create([
                'created_by' => $request->user()->id,
                'evaluation_year' => $data['evaluation_year'],
                'evaluation_quarter' => $data['evaluation_quarter'],
                'included_quarters' => $data['included_quarters'],
                'period_label' => $data['period_label'],
                'title' => $data['title'] ?: $this->defaultTitle($data),
                'status' => FormTemplateStatus::Draft,
            ]);

            $this->syncItems($template, $data['entries']);

            AuditLogger::log($request->user()->id, 'ipcr.form.created', $template, null, $request);

            return $template;
        });

        return redirect()
            ->route('admin.forms.show', $template)
            ->with('status', 'IPCR form created.');
    }

    public function show(IpcrFormTemplate $form): Response
    {
        $form->load('items');
        $totals = CommitmentWeightRules::totalsFromRows($form->items);

        return Inertia::render('Admin/Forms/Show', [
            'template' => [
                ...$form->toArray(),
                'weight_summary' => $totals,
                'meets_submit_requirement' => CommitmentWeightRules::meetsSpmsSplit(
                    $totals['core'],
                    $totals['strategic'],
                ),
            ],
        ]);
    }

    public function edit(IpcrFormTemplate $form): Response
    {
        $form->load('items');
        $totals = CommitmentWeightRules::totalsFromRows($form->items);

        return Inertia::render('Admin/Forms/Edit', [
            'template' => $form,
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

    public function update(Request $request, IpcrFormTemplate $form): RedirectResponse
    {
        $data = $this->validated($request);
        $quarters = IpcrIncludedQuarters::normalize($data['included_quarters'] ?? []);
        if ($quarters === []) {
            throw ValidationException::withMessages([
                'included_quarters' => 'Select at least one quarter to include in accomplishments.',
            ]);
        }
        $data['included_quarters'] = $quarters;
        $data['evaluation_quarter'] = IpcrIncludedQuarters::primaryQuarter($quarters);
        $data['period_label'] = IpcrIncludedQuarters::periodLabel((int) $data['evaluation_year'], $quarters);
        $this->assertWeights($data['entries'], false);

        DB::transaction(function () use ($request, $form, $data) {
            $form->update([
                'evaluation_year' => $data['evaluation_year'],
                'evaluation_quarter' => $data['evaluation_quarter'],
                'included_quarters' => $data['included_quarters'],
                'period_label' => $data['period_label'],
                'title' => $data['title'] ?: $this->defaultTitle($data),
            ]);

            $this->syncItems($form, $data['entries']);
            AuditLogger::log($request->user()->id, 'ipcr.form.updated', $form, null, $request);
        });

        return redirect()
            ->route('admin.forms.show', $form)
            ->with('status', 'IPCR form saved. Existing employee copies are not changed.');
    }

    public function destroy(Request $request, IpcrFormTemplate $form): RedirectResponse
    {
        $id = $form->id;

        DB::transaction(function () use ($form) {
            Commitment::query()
                ->where('ipcr_form_template_id', $form->id)
                ->update([
                    'ipcr_form_template_id' => null,
                    'ipcr_form_template_item_id' => null,
                ]);

            IpcrSubmission::query()
                ->where('ipcr_form_template_id', $form->id)
                ->update(['ipcr_form_template_id' => null]);

            $form->delete();
        });

        AuditLogger::log($request->user()->id, 'ipcr.form.deleted', null, ['id' => $id], $request);

        return redirect()
            ->route('dashboard', ['tab' => 'forms'])
            ->with('status', 'IPCR form removed. Employee copies were kept.');
    }

    /**
     * @return array{templates: list<array<string, mixed>>, period: array<string, mixed>, weightSummary: array<string, mixed>}
     */
    public function dashboardProps(): array
    {
        $year = (int) now()->year;
        $quarter = (int) ceil(now()->month / 3);

        $templates = IpcrFormTemplate::query()
            ->with('items')
            ->withCount('items')
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->orderByDesc('id')
            ->get()
            ->map(function (IpcrFormTemplate $template) {
                $totals = CommitmentWeightRules::totalsFromRows($template->items);

                return [
                    ...$template->toArray(),
                    'weight_summary' => $totals,
                    'meets_submit_requirement' => CommitmentWeightRules::meetsSpmsSplit(
                        $totals['core'],
                        $totals['strategic'],
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'formTemplates' => $templates,
            'formPeriod' => [
                'label' => 'Q'.$quarter.' '.$year,
                'year' => $year,
                'quarter' => $quarter,
            ],
            'formWeightSummary' => [
                'core' => 0,
                'strategic' => 0,
                'total' => 0,
                'core_remaining' => CommitmentWeightRules::CORE_CAP,
                'strategic_remaining' => CommitmentWeightRules::STRATEGIC_CAP,
                'core_cap' => CommitmentWeightRules::CORE_CAP,
                'strategic_cap' => CommitmentWeightRules::STRATEGIC_CAP,
                'meets_submit_requirement' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'included_quarters' => ['required', 'array', 'min:1'],
            'included_quarters.*' => ['integer', 'min:1', 'max:4'],
            'period_label' => ['nullable', 'string', 'max:32'],
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

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function assertWeights(array $entries, bool $mustMeetSplit): void
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

        if ($mustMeetSplit) {
            if (! CommitmentWeightRules::meetsSpmsSplit($core, $strategic)) {
                throw ValidationException::withMessages([
                    'entries' => sprintf(
                        'The form must total exactly %.0f%% core and %.0f%% strategic (currently %.2f%% / %.2f%%).',
                        CommitmentWeightRules::CORE_CAP,
                        CommitmentWeightRules::STRATEGIC_CAP,
                        $core,
                        $strategic
                    ),
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function syncItems(IpcrFormTemplate $template, array $entries): void
    {
        $existing = $template->items()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($entries) as $index => $entry) {
            $payload = [
                'sort_order' => $index,
                'function_group' => (int) ($entry['function_group'] ?? $index),
                'function_type' => $entry['function_type'],
                'title' => filled($entry['title'] ?? null) ? $entry['title'] : null,
                'description' => $entry['description'] ?? null,
                'weight' => $this->normalizeWeight($entry['weight'] ?? null),
                'annual_office_target' => $entry['annual_office_target'] ?? null,
                'individual_annual_targets' => $entry['individual_annual_targets'] ?? null,
            ];

            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            $item = $id > 0 ? $existing->get($id) : null;

            if ($item) {
                $item->update($payload);
                $keptIds[] = $item->id;
            } else {
                $created = $template->items()->create($payload);
                $keptIds[] = $created->id;
            }
        }

        $template->items()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(fn ($item) => $item->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function defaultTitle(array $data): string
    {
        return trim($data['period_label'].' IPCR');
    }

    private function normalizeWeight(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
