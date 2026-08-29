<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\CommitmentStatus;
use App\Enums\ReviewTransferRequestStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Models\SubmissionReviewTransferRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IpcrFormRatingCalculator;
use App\Services\IpcrSubmissionExportService;
use App\Services\SupervisorTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionReviewController extends Controller
{
    public function show(Request $request, IpcrSubmission $submission): Response
    {
        $supervisor = $request->user();
        abort_unless($submission->supervisor_id === $supervisor->id, 403);

        $submission->load(['employee', 'commitments.accomplishments']);

        return Inertia::render('Supervisor/SubmissionReview', [
            'submission' => $submission,
            'supervisors' => User::query()
                ->where('role', UserRole::Supervisor)
                ->where('id', '!=', $supervisor->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'pendingReviewTransfer' => SubmissionReviewTransferRequest::query()
                ->with(['toSupervisor:id,name'])
                ->where('ipcr_submission_id', $submission->id)
                ->where('status', ReviewTransferRequestStatus::Pending)
                ->first(),
        ]);
    }

    public function export(Request $request, IpcrSubmission $submission, string $format = 'xlsx'): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $submission = IpcrSubmissionExportService::authorizeApprovedExport($request, $submission);

        return IpcrSubmissionExportService::download($submission, $format);
    }

    public function print(Request $request, IpcrSubmission $submission): HttpResponse
    {
        $submission = IpcrSubmissionExportService::authorizeApprovedExport($request, $submission);

        return IpcrSubmissionExportService::inlinePrint($submission);
    }

    public function update(Request $request, IpcrSubmission $submission): RedirectResponse
    {
        $supervisor = $request->user();

        abort_unless($submission->supervisor_id === $supervisor->id, 403);
        abort_unless($submission->status === SubmissionStatus::InReview, 422);

        $base = $request->validate([
            'action' => ['required', 'in:approve,return,save'],
            'supervisor_feedback' => ['nullable', 'string', 'max:5000'],
            'commitments' => ['required', 'array', 'min:1'],
            'commitments.*.id' => ['nullable', 'integer'],
            'commitments.*.function_type' => ['required', 'in:core,strategic'],
            'commitments.*.function_group' => ['nullable', 'integer', 'min:0'],
            'commitments.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'commitments.*.title' => ['nullable', 'string', 'max:255'],
            'commitments.*.description' => ['nullable', 'string', 'max:8000'],
            'commitments.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commitments.*.annual_office_target' => ['nullable', 'string', 'max:255'],
            'commitments.*.individual_annual_targets' => ['nullable', 'string', 'max:255'],
            'commitments.*.rating_quality' => ['nullable', 'integer', 'min:0', 'max:5'],
            'commitments.*.rating_efficiency' => ['nullable', 'integer', 'min:0', 'max:5'],
            'commitments.*.rating_timeliness' => ['nullable', 'integer', 'min:0', 'max:5'],
            'commitments.*.rating_q3_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q3_actual' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q4_target' => ['nullable', 'integer', 'min:0'],
            'commitments.*.rating_q4_actual' => ['nullable', 'integer', 'min:0'],
        ]);

        $data = $base;

        if ($data['action'] === 'return') {
            $feedback = trim((string) ($data['supervisor_feedback'] ?? ''));
            if (strlen($feedback) < 20) {
                return back()->withErrors([
                    'supervisor_feedback' => 'Returning for revision requires clear guidance: please write at least 20 characters for the employee.',
                ]);
            }
            $data['supervisor_feedback'] = $feedback;
        }

        $submission->load('commitments');
        $existingById = $submission->commitments->keyBy('id');
        $template = $submission->commitments->first();

        if ($template === null) {
            return back()->withErrors(['commitments' => 'This submission has no commitments to rate.']);
        }

        foreach ($data['commitments'] as $index => $row) {
            if (! empty($row['id']) && ! $existingById->has((int) $row['id'])) {
                throw ValidationException::withMessages([
                    "commitments.{$index}.id" => 'Invalid commitment row for this submission.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $supervisor, $submission, $data, $existingById, $template) {
            $incomingIds = [];
            $synced = collect();

            foreach ($data['commitments'] as $index => $row) {
                $payload = [
                    'title' => filled($row['title'] ?? null) ? $row['title'] : null,
                    'description' => $row['description'] ?? null,
                    'function_type' => $row['function_type'],
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                    'function_group' => (int) ($row['function_group'] ?? $index),
                    'weight' => isset($row['weight']) && $row['weight'] !== '' && $row['weight'] !== null
                        ? round((float) $row['weight'], 2)
                        : null,
                    'annual_office_target' => $row['annual_office_target'] ?? null,
                    'individual_annual_targets' => $row['individual_annual_targets'] ?? null,
                    'status' => CommitmentStatus::InReview,
                ];

                if (! empty($row['id'])) {
                    $commitment = $existingById->get((int) $row['id']);
                    $commitment->update($payload);
                } else {
                    $commitment = $submission->commitments()->create([
                        ...$payload,
                        'user_id' => $submission->employee_id,
                        'batch_id' => $template->batch_id,
                        'evaluation_year' => $submission->evaluation_year,
                        'evaluation_quarter' => $submission->evaluation_quarter,
                        'period_label' => $template->period_label,
                        'progress' => 0,
                    ]);
                }

                $incomingIds[] = $commitment->id;
                $synced->push(['model' => $commitment->fresh(), 'row' => $row]);
            }

            $toDelete = $submission->commitments()
                ->whereNotIn('id', $incomingIds)
                ->get();

            foreach ($toDelete as $commitment) {
                $commitment->accomplishments()->delete();
                $commitment->delete();
            }

            $sumWeighted = 0.0;
            $hasWeighted = false;

            foreach ($synced as $item) {
                $commitment = $item['model']->fresh();
                $row = $item['row'];

                if ($data['action'] === 'approve' && IpcrFormRatingCalculator::isRateableRow($commitment, $row)) {
                    foreach (['rating_quality', 'rating_efficiency', 'rating_timeliness'] as $field) {
                        if (! isset($row[$field]) || ! is_numeric($row[$field])) {
                            $auto = IpcrFormRatingCalculator::totalsFromQ3Q4(
                                IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q3_target'] ?? null),
                                IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q3_actual'] ?? null),
                                IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q4_target'] ?? null),
                                IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q4_actual'] ?? null),
                            );
                            if ($auto['percent'] === null) {
                                throw ValidationException::withMessages([
                                    'commitments' => 'Every commitment with an Annual Office Target must have accomplishments so Quality, Efficiency, and Timeliness can be rated (0–5).',
                                ]);
                            }
                        }
                    }
                }

                IpcrFormRatingCalculator::applyRowRatings($commitment, $row, $data['action'] !== 'approve');
                $commitment->refresh();

                if ($commitment->rating_weighted !== null) {
                    $sumWeighted += (float) $commitment->rating_weighted;
                    $hasWeighted = true;
                }
            }

            if ($data['action'] === 'approve') {
                $submission->commitments()->update([
                    'status' => CommitmentStatus::Approved,
                ]);

                $submission->update([
                    'quality' => null,
                    'efficiency' => null,
                    'timeliness' => null,
                    'overall_rating' => $hasWeighted ? round($sumWeighted, 2) : null,
                    'supervisor_feedback' => $data['supervisor_feedback'] ?? null,
                    'status' => SubmissionStatus::Approved,
                    'reviewed_at' => now(),
                ]);
            } elseif ($data['action'] === 'return') {
                $submission->commitments()->update([
                    'status' => CommitmentStatus::Returned,
                ]);

                $submission->update([
                    'status' => SubmissionStatus::Returned,
                    'supervisor_feedback' => $data['supervisor_feedback'],
                    'reviewed_at' => now(),
                    'quality' => null,
                    'efficiency' => null,
                    'timeliness' => null,
                    'overall_rating' => null,
                ]);
            }

            AuditLogger::log(
                $supervisor->id,
                $data['action'] === 'save' ? 'ipcr.package_updated' : 'ipcr.reviewed',
                $submission,
                ['action' => $data['action']],
                $request,
            );
        });

        if (in_array($data['action'], ['approve', 'return'], true)) {
            app(SupervisorTransferService::class)->notifyReviewCompleted(
                $submission->fresh(['employee', 'supervisor']),
                $data['action'],
            );
        }

        return back()->with('status', $data['action'] === 'save'
            ? 'Package updates saved.'
            : 'Review saved.');
    }
}
