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
            'commitments.*.title' => ['required', 'string', 'max:255'],
            'commitments.*.description' => ['nullable', 'string', 'max:8000'],
            'commitments.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commitments.*.annual_office_target' => ['nullable', 'string', 'max:255'],
            'commitments.*.individual_annual_targets' => ['nullable', 'string', 'max:255'],
            'commitments.*.rating_quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'commitments.*.rating_efficiency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'commitments.*.rating_timeliness' => ['nullable', 'integer', 'min:1', 'max:5'],
            'commitments.*.rating_q3_target' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q3_actual' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q4_target' => ['nullable', 'numeric', 'min:0'],
            'commitments.*.rating_q4_actual' => ['nullable', 'numeric', 'min:0'],
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

            foreach ($data['commitments'] as $row) {
                $payload = [
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'function_type' => $row['function_type'],
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

            if ($data['action'] === 'approve') {
                $sumWeighted = 0.0;
                $hasWeighted = false;

                foreach ($synced as $item) {
                    $commitment = $item['model'];
                    $row = $item['row'];

                    $q3Target = IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q3_target'] ?? null);
                    $q3Actual = IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q3_actual'] ?? null);
                    $q4Target = IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q4_target'] ?? null);
                    $q4Actual = IpcrFormRatingCalculator::nullableWholeNumber($row['rating_q4_actual'] ?? null);

                    $totals = IpcrFormRatingCalculator::totalsFromQ3Q4(
                        $q3Target,
                        $q3Actual,
                        $q4Target,
                        $q4Actual,
                    );

                    if ($commitment->weight === null) {
                        $commitment->update([
                            'rating_q3_target' => $q3Target,
                            'rating_q3_actual' => $q3Actual,
                            'rating_q4_target' => $q4Target,
                            'rating_q4_actual' => $q4Actual,
                            'rating_target_total' => $totals['target_total'],
                            'rating_actual_total' => $totals['actual_total'],
                            'rating_percent' => $totals['percent'],
                            'rating_quality' => null,
                            'rating_efficiency' => null,
                            'rating_timeliness' => null,
                            'rating_average' => null,
                            'rating_weighted' => null,
                            'remarks' => null,
                            'status' => CommitmentStatus::Approved,
                        ]);

                        continue;
                    }

                    foreach (['rating_quality', 'rating_efficiency', 'rating_timeliness'] as $field) {
                        if (! isset($row[$field]) || ! is_numeric($row[$field])) {
                            throw ValidationException::withMessages([
                                'commitments' => 'Every commitment with a weight must have Quality, Efficiency, and Timeliness ratings (1–5).',
                            ]);
                        }
                    }

                    $scored = IpcrFormRatingCalculator::scoreRowFromRatings(
                        (int) $row['rating_quality'],
                        (int) $row['rating_efficiency'],
                        (int) $row['rating_timeliness'],
                        (float) $commitment->weight,
                    );

                    $commitment->update([
                        'rating_q3_target' => $q3Target,
                        'rating_q3_actual' => $q3Actual,
                        'rating_q4_target' => $q4Target,
                        'rating_q4_actual' => $q4Actual,
                        'rating_target_total' => $totals['target_total'],
                        'rating_actual_total' => $totals['actual_total'],
                        'rating_percent' => $totals['percent'],
                        'rating_quality' => $scored['quality'],
                        'rating_efficiency' => $scored['efficiency'],
                        'rating_timeliness' => $scored['timeliness'],
                        'rating_average' => $scored['average'],
                        'rating_weighted' => $scored['weighted'],
                        'remarks' => null,
                        'status' => CommitmentStatus::Approved,
                    ]);

                    $sumWeighted += $scored['weighted'] ?? 0.0;
                    $hasWeighted = true;
                }

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
                    'rating_actual_total' => null,
                    'rating_target_total' => null,
                    'rating_q3_target' => null,
                    'rating_q3_actual' => null,
                    'rating_q4_target' => null,
                    'rating_q4_actual' => null,
                    'rating_percent' => null,
                    'rating_quality' => null,
                    'rating_efficiency' => null,
                    'rating_timeliness' => null,
                    'rating_average' => null,
                    'rating_weighted' => null,
                    'remarks' => null,
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
