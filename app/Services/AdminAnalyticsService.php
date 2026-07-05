<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AdminAnalyticsService
{
    private const MONTHS = 12;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $monthKeys = $this->monthKeys();
        $monthLabels = $this->monthLabels($monthKeys);

        $submitted = $this->monthlyCounts(
            IpcrSubmission::query()->whereNotNull('submitted_at'),
            'submitted_at',
            $monthKeys,
        );

        $approved = $this->monthlyCounts(
            IpcrSubmission::query()
                ->where('status', SubmissionStatus::Approved)
                ->whereNotNull('reviewed_at'),
            'reviewed_at',
            $monthKeys,
        );

        $returned = $this->monthlyCounts(
            IpcrSubmission::query()->where('status', SubmissionStatus::Returned),
            'updated_at',
            $monthKeys,
        );

        $registrations = $this->monthlyCounts(
            User::query(),
            'created_at',
            $monthKeys,
        );

        $totalSubmissions = IpcrSubmission::count();
        $approvedCount = IpcrSubmission::where('status', SubmissionStatus::Approved)->count();
        $returnedCount = IpcrSubmission::where('status', SubmissionStatus::Returned)->count();
        $inReviewCount = IpcrSubmission::where('status', SubmissionStatus::InReview)->count();

        $reviewedTotal = max($approvedCount + $returnedCount, 1);

        return [
            'summary' => [
                'registrationsThisMonth' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'submissionsThisMonth' => IpcrSubmission::where('submitted_at', '>=', now()->startOfMonth())->count(),
                'approvedThisMonth' => IpcrSubmission::query()
                    ->where('status', SubmissionStatus::Approved)
                    ->where('reviewed_at', '>=', now()->startOfMonth())
                    ->count(),
                'returnedThisMonth' => IpcrSubmission::query()
                    ->where('status', SubmissionStatus::Returned)
                    ->where('updated_at', '>=', now()->startOfMonth())
                    ->count(),
                'approvalRate' => round(($approvedCount / $reviewedTotal) * 100, 1),
                'returnRate' => round(($returnedCount / $reviewedTotal) * 100, 1),
                'averageRating' => round(
                    (float) (IpcrSubmission::query()
                        ->where('status', SubmissionStatus::Approved)
                        ->whereNotNull('overall_rating')
                        ->avg('overall_rating') ?? 0),
                    2,
                ),
                'inReview' => $inReviewCount,
                'totalSubmissions' => $totalSubmissions,
            ],
            'userRegistrations' => [
                'labels' => $monthLabels,
                'values' => $registrations,
            ],
            'evaluationActivity' => [
                'labels' => $monthLabels,
                'submitted' => $submitted,
                'approved' => $approved,
                'returned' => $returned,
            ],
            'submissionsByStatus' => $this->submissionsByStatus(),
            'ratingDistribution' => $this->ratingDistribution(),
        ];
    }

    /**
     * @return list<string>
     */
    private function monthKeys(): array
    {
        $keys = [];

        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $keys[] = now()->subMonths($i)->format('Y-m');
        }

        return $keys;
    }

    /**
     * @param  list<string>  $monthKeys
     * @return list<string>
     */
    private function monthLabels(array $monthKeys): array
    {
        return array_map(
            fn (string $key) => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
            $monthKeys,
        );
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $monthKeys
     * @return list<int>
     */
    private function monthlyCounts(Builder $query, string $column, array $monthKeys): array
    {
        $start = Carbon::createFromFormat('Y-m', $monthKeys[0])->startOfMonth();

        $grouped = (clone $query)
            ->where($column, '>=', $start)
            ->get([$column])
            ->groupBy(fn ($row) => $row->{$column}?->format('Y-m') ?? '');

        return array_map(
            fn (string $key) => isset($grouped[$key]) ? $grouped[$key]->count() : 0,
            $monthKeys,
        );
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function submissionsByStatus(): array
    {
        $statuses = [
            SubmissionStatus::Pending,
            SubmissionStatus::InReview,
            SubmissionStatus::Approved,
            SubmissionStatus::Returned,
        ];

        $labels = ['Pending', 'In review', 'Approved', 'Returned'];
        $values = array_map(
            fn (SubmissionStatus $status) => IpcrSubmission::where('status', $status)->count(),
            $statuses,
        );

        return compact('labels', 'values');
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function ratingDistribution(): array
    {
        $labels = ['Below 3.0', '3.0 – 3.99', '4.0 – 4.49', '4.5 – 4.99', '5.0'];
        $values = array_fill(0, count($labels), 0);

        IpcrSubmission::query()
            ->where('status', SubmissionStatus::Approved)
            ->whereNotNull('overall_rating')
            ->pluck('overall_rating')
            ->each(function ($rating) use (&$values) {
                $score = (float) $rating;
                $index = match (true) {
                    $score < 3.0 => 0,
                    $score < 4.0 => 1,
                    $score < 4.5 => 2,
                    $score < 5.0 => 3,
                    default => 4,
                };
                $values[$index]++;
            });

        return compact('labels', 'values');
    }
}
