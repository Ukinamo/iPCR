<?php

namespace App\Services;

use App\Enums\CommitmentStatus;
use App\Enums\FormTemplateStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\Commitment;
use App\Models\IpcrFormTemplate;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Notifications\IpcrFormAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IpcrFormTemplateProvisioner
{
    /**
     * @param  list<int>  $supervisorIds
     */
    public function assign(IpcrFormTemplate $template, array $supervisorIds, bool $notify = true): void
    {
        $template->loadMissing('items', 'supervisors');
        $supervisorIds = array_values(array_unique(array_map('intval', $supervisorIds)));
        $previousIds = $template->supervisors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $newlyAssigned = array_values(array_diff($supervisorIds, $previousIds));
        $removedIds = array_values(array_diff($previousIds, $supervisorIds));

        DB::transaction(function () use ($template, $supervisorIds, $removedIds) {
            $existingAssignedAt = $template->supervisors
                ->mapWithKeys(fn (User $supervisor) => [
                    (int) $supervisor->id => $supervisor->pivot->assigned_at,
                ]);

            $sync = [];
            foreach ($supervisorIds as $supervisorId) {
                $sync[$supervisorId] = [
                    'assigned_at' => $existingAssignedAt->get($supervisorId) ?? now(),
                ];
            }

            $template->supervisors()->sync($sync);
            $template->update([
                'status' => $supervisorIds === [] ? FormTemplateStatus::Draft : FormTemplateStatus::Assigned,
                'assigned_at' => $supervisorIds === []
                    ? null
                    : ($template->assigned_at ?? now()),
            ]);

            if ($removedIds !== []) {
                $this->removeDraftsForSupervisors($template, $removedIds);
            }

            $employees = User::query()
                ->where('role', UserRole::Employee)
                ->whereIn('supervisor_id', $supervisorIds)
                ->get();

            foreach ($employees as $employee) {
                $this->provisionForEmployee($template, $employee);
            }
        });

        if ($notify && $newlyAssigned !== []) {
            $this->notifyAssignment($template->fresh(['items']), $newlyAssigned);
        }
    }

    /**
     * Snapshot an admin template into a new employee package. Later edits do not change the template.
     */
    public function copyTemplateForEmployee(
        IpcrFormTemplate $template,
        User $employee,
        int $year,
        int $quarter,
    ): IpcrSubmission {
        $template->loadMissing('items');
        $batchId = (string) Str::uuid();
        $quarters = \App\Support\IpcrIncludedQuarters::existingOrDefault($template->included_quarters);
        $year = (int) $template->evaluation_year;
        $quarter = \App\Support\IpcrIncludedQuarters::primaryQuarter($quarters);
        $periodLabel = \App\Support\IpcrIncludedQuarters::periodLabel($year, $quarters);

        return DB::transaction(function () use ($template, $employee, $year, $quarter, $batchId, $periodLabel, $quarters) {
            $submission = IpcrSubmission::create([
                'employee_id' => $employee->id,
                'supervisor_id' => $employee->supervisor_id,
                'batch_id' => $batchId,
                'title' => $template->title ?: 'IPCR form',
                'ipcr_form_template_id' => $template->id,
                'evaluation_year' => $year,
                'evaluation_quarter' => $quarter,
                'included_quarters' => $quarters,
                'status' => SubmissionStatus::Pending,
            ]);

            foreach ($template->items as $index => $item) {
                Commitment::create([
                    'user_id' => $employee->id,
                    'batch_id' => $batchId,
                    'ipcr_form_template_id' => $template->id,
                    'ipcr_form_template_item_id' => null,
                    'ipcr_submission_id' => $submission->id,
                    'evaluation_year' => $year,
                    'evaluation_quarter' => $quarter,
                    'period_label' => $periodLabel,
                    'title' => $item->title,
                    'description' => $item->description,
                    'function_type' => $item->function_type,
                    'sort_order' => (int) ($item->sort_order ?? $index),
                    'function_group' => (int) ($item->function_group ?? $index),
                    'weight' => $item->weight,
                    'annual_office_target' => $item->annual_office_target,
                    'individual_annual_targets' => $item->individual_annual_targets,
                    'status' => CommitmentStatus::Draft,
                    'progress' => 0,
                ]);
            }

            return $submission;
        });
    }

    public function createBlankPackage(
        User $employee,
        int $year,
        int $quarter,
        string $title,
        array $entries,
    ): IpcrSubmission {
        $batchId = (string) Str::uuid();
        $quarters = \App\Support\IpcrIncludedQuarters::existingOrDefault(null);
        $periodLabel = \App\Support\IpcrIncludedQuarters::periodLabel($year, $quarters);

        return DB::transaction(function () use ($employee, $year, $quarter, $title, $entries, $batchId, $periodLabel, $quarters) {
            $submission = IpcrSubmission::create([
                'employee_id' => $employee->id,
                'supervisor_id' => $employee->supervisor_id,
                'batch_id' => $batchId,
                'title' => $title !== '' ? $title : 'My IPCR form',
                'ipcr_form_template_id' => null,
                'evaluation_year' => $year,
                'evaluation_quarter' => $quarter,
                'included_quarters' => $quarters,
                'status' => SubmissionStatus::Pending,
            ]);

            foreach (array_values($entries) as $index => $entry) {
                Commitment::create([
                    'user_id' => $employee->id,
                    'batch_id' => $batchId,
                    'ipcr_form_template_id' => null,
                    'ipcr_form_template_item_id' => null,
                    'ipcr_submission_id' => $submission->id,
                    'evaluation_year' => $year,
                    'evaluation_quarter' => $quarter,
                    'period_label' => $periodLabel,
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
                    'status' => CommitmentStatus::Draft,
                    'progress' => 0,
                ]);
            }

            return $submission;
        });
    }

    public function provisionAssignedForEmployee(User $employee): void
    {
        // Employees now choose or create a form. Admin templates are copied once and never synced back.
    }

    public function provisionForEmployee(IpcrFormTemplate $template, User $employee): void
    {
        if ($this->periodIsLocked($employee->id, (int) $template->evaluation_year, (int) $template->evaluation_quarter)) {
            return;
        }

        $template->loadMissing('items');
        $items = $template->items;

        $existing = Commitment::query()
            ->where('user_id', $employee->id)
            ->where('evaluation_year', $template->evaluation_year)
            ->where('evaluation_quarter', $template->evaluation_quarter)
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
            ->get();

        $batchId = $existing->first()?->batch_id ?: (string) Str::uuid();
        $submissionId = $existing->first()?->ipcr_submission_id;
        $keptIds = [];

        foreach ($items as $index => $item) {
            $commitment = $existing->firstWhere('ipcr_form_template_item_id', $item->id);

            if ($commitment === null) {
                $commitment = $existing->first(function (Commitment $row) use ($item, $keptIds) {
                    return ! in_array($row->id, $keptIds, true)
                        && $row->ipcr_form_template_item_id === null
                        && $row->function_type === $item->function_type
                        && $row->title === $item->title;
                });
            }

            $payload = [
                'user_id' => $employee->id,
                'batch_id' => $batchId,
                'ipcr_form_template_id' => $template->id,
                'ipcr_form_template_item_id' => $item->id,
                'ipcr_submission_id' => $submissionId,
                'evaluation_year' => $template->evaluation_year,
                'evaluation_quarter' => $template->evaluation_quarter,
                'period_label' => $template->period_label,
                'title' => $item->title,
                'description' => $item->description,
                'function_type' => $item->function_type,
                'sort_order' => (int) ($item->sort_order ?? $index),
                'function_group' => (int) ($item->function_group ?? $index),
                'weight' => $item->weight,
                'annual_office_target' => $item->annual_office_target,
                'individual_annual_targets' => $item->individual_annual_targets,
                'status' => $commitment?->status ?? CommitmentStatus::Draft,
                'progress' => $commitment?->progress ?? 0,
            ];

            if ($commitment) {
                $commitment->update($payload);
                $keptIds[] = $commitment->id;
            } else {
                $created = Commitment::create($payload);
                $keptIds[] = $created->id;
            }
        }

        $existing
            ->reject(fn (Commitment $row) => in_array($row->id, $keptIds, true))
            ->each(function (Commitment $row) {
                $row->accomplishments()->delete();
                $row->delete();
            });
    }

    /**
     * @param  list<int>  $supervisorIds
     */
    private function removeDraftsForSupervisors(IpcrFormTemplate $template, array $supervisorIds): void
    {
        $employeeIds = User::query()
            ->where('role', UserRole::Employee)
            ->whereIn('supervisor_id', $supervisorIds)
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return;
        }

        Commitment::query()
            ->where('ipcr_form_template_id', $template->id)
            ->whereIn('user_id', $employeeIds)
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
            ->get()
            ->each(function (Commitment $commitment) {
                $commitment->accomplishments()->delete();
                $commitment->delete();
            });
    }

    private function periodIsLocked(int $employeeId, int $year, int $quarter): bool
    {
        $submission = IpcrSubmission::query()
            ->where('employee_id', $employeeId)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->first();

        if ($submission && in_array($submission->status, [SubmissionStatus::InReview, SubmissionStatus::Approved], true)) {
            return true;
        }

        return Commitment::query()
            ->where('user_id', $employeeId)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->where('status', CommitmentStatus::InReview)
            ->exists();
    }

    /**
     * @param  list<int>  $supervisorIds
     */
    private function notifyAssignment(IpcrFormTemplate $template, array $supervisorIds): void
    {
        User::query()
            ->whereIn('id', $supervisorIds)
            ->each(fn (User $supervisor) => $supervisor->notify(new IpcrFormAssignedNotification($template, 'supervisor')));

        User::query()
            ->where('role', UserRole::Employee)
            ->whereIn('supervisor_id', $supervisorIds)
            ->each(fn (User $employee) => $employee->notify(new IpcrFormAssignedNotification($template, 'employee')));
    }
}
