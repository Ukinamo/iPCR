<?php

namespace App\Services;

use App\Enums\CommitmentStatus;
use App\Models\Commitment;
use Illuminate\Support\Collection;

/**
 * CHED / SPMS-style split: core functions 60%, strategic functions 40% of the IPCR weight for a period.
 */
final class CommitmentWeightRules
{
    public const CORE_CAP = 60.0;

    public const STRATEGIC_CAP = 40.0;

    /**
     * @return array{core: float, strategic: float, total: float}
     */
    public static function totalsForEditablePeriod(int $userId, int $year, int $quarter, ?int $excludeCommitmentId = null): array
    {
        $exclude = $excludeCommitmentId !== null ? [$excludeCommitmentId] : [];

        return self::totalsExcludingCommitmentIds($userId, $year, $quarter, $exclude);
    }

    /**
     * @param  list<int>  $excludeCommitmentIds
     * @return array{core: float, strategic: float, total: float}
     */
    public static function totalsExcludingCommitmentIds(int $userId, int $year, int $quarter, array $excludeCommitmentIds = []): array
    {
        $q = Commitment::query()
            ->where('user_id', $userId)
            ->where('evaluation_year', $year)
            ->where('evaluation_quarter', $quarter)
            ->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned]);

        if ($excludeCommitmentIds !== []) {
            $q->whereNotIn('id', $excludeCommitmentIds);
        }

        return self::totalsFromCollection($q->get());
    }

    /**
     * Draft + returned commitments that will be included in the next submission.
     *
     * @return array{core: float, strategic: float, total: float}
     */
    public static function totalsForSubmissionBatch(Collection $commitments): array
    {
        return self::totalsFromCollection(
            $commitments->whereIn('status', [CommitmentStatus::Draft, CommitmentStatus::Returned])
        );
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array{core: float, strategic: float, total: float}
     */
    public static function totalsFromRows(iterable $rows): array
    {
        $core = 0.0;
        $strategic = 0.0;

        foreach ($rows as $row) {
            $type = is_array($row) ? ($row['function_type'] ?? null) : ($row->function_type ?? null);
            $weight = (float) (is_array($row) ? ($row['weight'] ?? 0) : ($row->weight ?? 0));

            if ($type === 'core') {
                $core += $weight;
            } elseif ($type === 'strategic') {
                $strategic += $weight;
            }
        }

        return [
            'core' => round($core, 2),
            'strategic' => round($strategic, 2),
            'total' => round($core + $strategic, 2),
        ];
    }

    /**
     * @return array{core: float, strategic: float, total: float, core_remaining: float, strategic_remaining: float}
     */
    public static function summaryForEmployee(int $userId, int $year, int $quarter): array
    {
        $totals = self::totalsForEditablePeriod($userId, $year, $quarter);

        return [
            ...$totals,
            'core_remaining' => round(max(0, self::CORE_CAP - $totals['core']), 2),
            'strategic_remaining' => round(max(0, self::STRATEGIC_CAP - $totals['strategic']), 2),
            'core_cap' => self::CORE_CAP,
            'strategic_cap' => self::STRATEGIC_CAP,
            'meets_submit_requirement' => self::meetsSpmsSplit($totals['core'], $totals['strategic']),
        ];
    }

    public static function meetsSpmsSplit(float $core, float $strategic): bool
    {
        return self::nearlyEqual($core, self::CORE_CAP) && self::nearlyEqual($strategic, self::STRATEGIC_CAP);
    }

    /**
     * After adding or changing weight for one commitment, ensure caps are not exceeded.
     */
    public static function assertCapsRespected(float $coreTotal, float $strategicTotal): ?string
    {
        if ($coreTotal - self::CORE_CAP > 0.009) {
            return 'Core commitments for this quarter cannot exceed 60% total weight (SPMS).';
        }

        if ($strategicTotal - self::STRATEGIC_CAP > 0.009) {
            return 'Strategic commitments for this quarter cannot exceed 40% total weight (SPMS).';
        }

        return null;
    }

    public static function submissionErrorIfInvalid(float $core, float $strategic): ?string
    {
        if (! self::meetsSpmsSplit($core, $strategic)) {
            return sprintf(
                'Before submitting, your draft commitments must total exactly %.0f%% core and %.0f%% strategic for this quarter (currently %.2f%% / %.2f%%).',
                self::CORE_CAP,
                self::STRATEGIC_CAP,
                $core,
                $strategic
            );
        }

        return null;
    }

    private static function totalsFromCollection(Collection $rows): array
    {
        $core = (float) $rows->where('function_type', 'core')->sum(fn ($row) => $row->weight ?? 0);
        $strategic = (float) $rows->where('function_type', 'strategic')->sum(fn ($row) => $row->weight ?? 0);

        return [
            'core' => round($core, 2),
            'strategic' => round($strategic, 2),
            'total' => round($core + $strategic, 2),
        ];
    }

    private static function nearlyEqual(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }
}
