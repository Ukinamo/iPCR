<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\StoMonitoringReportType;
use App\Http\Controllers\Controller;
use App\Models\StoMonitoringForm;
use App\Services\AuditLogger;
use App\Services\RegisterReportReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoMonitoringController extends Controller
{
    public function create(Request $request): Response
    {
        $type = StoMonitoringReportType::tryFrom((string) $request->query('type', ''))
            ?? StoMonitoringReportType::Stufap;

        return Inertia::render('Supervisor/StoMonitoring/Edit', [
            'formRecord' => null,
            'reportType' => $type->value,
            'periodYear' => (int) now()->year,
            'viewer' => 'supervisor',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $type = StoMonitoringReportType::from($data['report_type']);

        $form = DB::transaction(function () use ($request, $data, $type) {
            $form = StoMonitoringForm::create([
                'supervisor_id' => $request->user()->id,
                'report_type' => $type,
                'title' => $data['title'] ?: $type->defaultTitle(),
                'office_name' => $data['office_name'] ?: 'CHEDRO : MIMAROPA',
                'evaluation_year' => $data['evaluation_year'],
            ]);
            $this->syncEntries($form, $data['entries'] ?? []);
            AuditLogger::log($request->user()->id, 'sto_monitoring.created', $form, ['type' => $type->value], $request);

            return $form;
        });

        if ($request->boolean('submit')) {
            app(RegisterReportReviewService::class)->submit($form, $request->user(), $request);

            return redirect()
                ->route('dashboard', ['tab' => 'programs'])
                ->with('status', 'STO monitoring report submitted for administrator review.');
        }

        return redirect()
            ->route('supervisor.sto-monitoring.edit', $form)
            ->with('status', 'STO monitoring report saved.');
    }

    public function edit(Request $request, StoMonitoringForm $stoMonitoringForm): Response
    {
        $this->authorizeForm($request, $stoMonitoringForm);
        $stoMonitoringForm->load('entries');

        return Inertia::render('Supervisor/StoMonitoring/Edit', [
            'formRecord' => $stoMonitoringForm,
            'reportType' => $stoMonitoringForm->report_type->value,
            'periodYear' => (int) $stoMonitoringForm->evaluation_year,
            'viewer' => 'supervisor',
        ]);
    }

    public function update(Request $request, StoMonitoringForm $stoMonitoringForm): RedirectResponse
    {
        $this->authorizeForm($request, $stoMonitoringForm);
        abort_unless($stoMonitoringForm->supervisorCanEdit(), 422, 'This report is with the administrator and cannot be edited.');
        $data = $this->validated($request);
        $type = $stoMonitoringForm->report_type;

        DB::transaction(function () use ($request, $stoMonitoringForm, $data, $type) {
            $stoMonitoringForm->update([
                'title' => $data['title'] ?: $type->defaultTitle(),
                'office_name' => $data['office_name'] ?: 'CHEDRO : MIMAROPA',
                'evaluation_year' => $data['evaluation_year'],
            ]);
            $this->syncEntries($stoMonitoringForm, $data['entries'] ?? []);
            AuditLogger::log($request->user()->id, 'sto_monitoring.updated', $stoMonitoringForm, null, $request);
        });

        if ($request->boolean('submit')) {
            app(RegisterReportReviewService::class)->submit($stoMonitoringForm->fresh(), $request->user(), $request);

            return redirect()
                ->route('dashboard', ['tab' => 'programs'])
                ->with('status', 'STO monitoring report submitted for administrator review.');
        }

        return back()->with('status', 'STO monitoring report saved.');
    }

    public function destroy(Request $request, StoMonitoringForm $stoMonitoringForm): RedirectResponse
    {
        $this->authorizeForm($request, $stoMonitoringForm);
        abort_unless($stoMonitoringForm->supervisorCanEdit(), 422, 'This report cannot be deleted while it is with the administrator.');

        DB::transaction(function () use ($request, $stoMonitoringForm) {
            $stoMonitoringForm->entries()->delete();
            AuditLogger::log($request->user()->id, 'sto_monitoring.deleted', null, [
                'id' => $stoMonitoringForm->id,
                'title' => $stoMonitoringForm->title,
            ], $request);
            $stoMonitoringForm->delete();
        });

        return redirect()
            ->route('dashboard', ['tab' => 'programs'])
            ->with('status', 'STO monitoring report deleted.');
    }

    private function authorizeForm(Request $request, StoMonitoringForm $form): void
    {
        abort_unless($form->supervisor_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'report_type' => ['required', Rule::enum(StoMonitoringReportType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'office_name' => ['nullable', 'string', 'max:255'],
            'evaluation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entries' => ['nullable', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.hei_name' => ['nullable', 'string', 'max:255'],
            'entries.*.monitored_item' => ['nullable', 'string', 'max:255'],
            'entries.*.grantee_count' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'entries.*.date_monitored' => ['nullable', 'string', 'max:64'],
            'entries.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function syncEntries(StoMonitoringForm $form, array $entries): void
    {
        $existing = $form->entries()->get()->keyBy('id');
        $keptIds = [];

        $sort = 0;
        foreach (array_values($entries) as $entry) {
            $payload = [
                'hei_name' => $this->nullableString($entry['hei_name'] ?? null),
                'monitored_item' => $this->nullableString($entry['monitored_item'] ?? null),
                'grantee_count' => isset($entry['grantee_count']) && $entry['grantee_count'] !== '' && $entry['grantee_count'] !== null
                    ? (int) $entry['grantee_count']
                    : null,
                'date_monitored' => $this->nullableString($entry['date_monitored'] ?? null),
                'remarks' => $this->nullableString($entry['remarks'] ?? null),
            ];

            if (
                $payload['hei_name'] === null
                && $payload['monitored_item'] === null
                && $payload['grantee_count'] === null
                && $payload['date_monitored'] === null
                && $payload['remarks'] === null
            ) {
                continue;
            }

            $payload['sort_order'] = $sort;
            $sort++;

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

        $form->entries()->whereNotIn('id', $keptIds)->delete();
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
