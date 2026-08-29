<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ProgramEvaluationForm;
use App\Services\AuditLogger;
use App\Services\RegisterReportReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProgramEvaluationController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Supervisor/ProgramEvaluations/Edit', [
            'formRecord' => null,
            'periodYear' => (int) now()->year,
            'viewer' => 'supervisor',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $form = DB::transaction(function () use ($request, $data) {
            $form = ProgramEvaluationForm::create([
                'supervisor_id' => $request->user()->id,
                'title' => $data['title'] ?: 'Programs Monitored/Evaluated/Inspected',
                'office_name' => $data['office_name'] ?: 'CHEDRO : MIMAROPA',
                'evaluation_year' => $data['evaluation_year'],
            ]);

            $this->syncEntries($form, $data['entries'] ?? []);
            AuditLogger::log($request->user()->id, 'program_evaluation.created', $form, null, $request);

            return $form;
        });

        if ($request->boolean('submit')) {
            app(RegisterReportReviewService::class)->submit($form, $request->user(), $request);

            return redirect()
                ->route('dashboard', ['tab' => 'programs'])
                ->with('status', 'Programs evaluated form submitted for administrator review.');
        }

        return redirect()
            ->route('supervisor.program-evaluations.edit', $form)
            ->with('status', 'Programs evaluated form saved.');
    }

    public function edit(Request $request, ProgramEvaluationForm $form): Response
    {
        $this->authorizeForm($request, $form);
        $form->load('entries');

        return Inertia::render('Supervisor/ProgramEvaluations/Edit', [
            'formRecord' => $form,
            'periodYear' => (int) $form->evaluation_year,
            'viewer' => 'supervisor',
        ]);
    }

    public function update(Request $request, ProgramEvaluationForm $form): RedirectResponse
    {
        $this->authorizeForm($request, $form);
        abort_unless($form->supervisorCanEdit(), 422, 'This report is with the administrator and cannot be edited.');
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $form, $data) {
            $form->update([
                'title' => $data['title'] ?: 'Programs Monitored/Evaluated/Inspected',
                'office_name' => $data['office_name'] ?: 'CHEDRO : MIMAROPA',
                'evaluation_year' => $data['evaluation_year'],
            ]);
            $this->syncEntries($form, $data['entries'] ?? []);
            AuditLogger::log($request->user()->id, 'program_evaluation.updated', $form, null, $request);
        });

        if ($request->boolean('submit')) {
            app(RegisterReportReviewService::class)->submit($form->fresh(), $request->user(), $request);

            return redirect()
                ->route('dashboard', ['tab' => 'programs'])
                ->with('status', 'Programs evaluated form submitted for administrator review.');
        }

        return back()->with('status', 'Programs evaluated form saved.');
    }

    public function destroy(Request $request, ProgramEvaluationForm $form): RedirectResponse
    {
        $this->authorizeForm($request, $form);
        abort_unless($form->supervisorCanEdit(), 422, 'This report cannot be deleted while it is with the administrator.');

        DB::transaction(function () use ($request, $form) {
            $form->entries()->delete();
            AuditLogger::log($request->user()->id, 'program_evaluation.deleted', null, [
                'id' => $form->id,
                'title' => $form->title,
            ], $request);
            $form->delete();
        });

        return redirect()
            ->route('dashboard', ['tab' => 'programs'])
            ->with('status', 'Programs evaluated form deleted.');
    }

    private function authorizeForm(Request $request, ProgramEvaluationForm $form): void
    {
        abort_unless($form->supervisor_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'office_name' => ['nullable', 'string', 'max:255'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.institutional_code' => ['nullable', 'string', 'max:64'],
            'entries.*.hei_name' => ['nullable', 'string', 'max:255'],
            'entries.*.institutional_type' => ['nullable', 'string', 'max:32'],
            'entries.*.program_name' => ['nullable', 'string', 'max:500'],
            'entries.*.program_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'entries.*.purpose' => ['nullable', 'string', 'max:255'],
            'entries.*.effectivity_ay' => ['nullable', 'string', 'max:64'],
            'entries.*.date_applied' => ['nullable', 'string', 'max:64'],
            'entries.*.date_evaluated' => ['nullable', 'string', 'max:64'],
            'entries.*.result' => ['nullable', 'string', 'max:64'],
            'entries.*.final_recommendation' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function syncEntries(ProgramEvaluationForm $form, array $entries): void
    {
        $existing = $form->entries()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($entries) as $index => $entry) {
            $payload = [
                'sort_order' => $index,
                'institutional_code' => $this->nullableString($entry['institutional_code'] ?? null),
                'hei_name' => $this->nullableString($entry['hei_name'] ?? null),
                'institutional_type' => $this->nullableString($entry['institutional_type'] ?? null),
                'program_name' => $this->nullableString($entry['program_name'] ?? null),
                'program_count' => isset($entry['program_count']) && $entry['program_count'] !== ''
                    ? (int) $entry['program_count']
                    : null,
                'purpose' => $this->nullableString($entry['purpose'] ?? null),
                'effectivity_ay' => $this->nullableString($entry['effectivity_ay'] ?? null),
                'date_applied' => $this->nullableString($entry['date_applied'] ?? null),
                'date_evaluated' => $this->nullableString($entry['date_evaluated'] ?? null),
                'result' => $this->nullableString($entry['result'] ?? null),
                'final_recommendation' => $this->nullableString($entry['final_recommendation'] ?? null),
            ];

            $id = isset($entry['id']) ? (int) $entry['id'] : 0;
            $row = $id > 0 ? $existing->get($id) : null;

            if ($row) {
                $row->update($payload);
                $keptIds[] = $row->id;
            } else {
                $created = $form->entries()->create($payload);
                $keptIds[] = $created->id;
            }
        }

        $form->entries()
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
