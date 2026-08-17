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

    public function provisionAssignedForEmployee(User $employee): void
    {
        if (! $employee->isEmployee() || $employee->supervisor_id === null) {
            return;
        }

        $templates = IpcrFormTemplate::query()
            ->with('items')
            ->where('status', FormTemplateStatus::Assigned)
            ->whereHas('supervisors', fn ($q) => $q->where('users.id', $employee->supervisor_id))
            ->get();

        foreach ($templates as $template) {
            $this->provisionForEmployee($template, $employee);
        }
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

        foreach ($items as $item) {
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
