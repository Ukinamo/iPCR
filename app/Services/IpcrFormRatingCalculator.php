<?php

namespace App\Services;

/**
 * IPCR Form 1 style ratings (CHED sample workbook):
 * - Accomplishment ratio N = total_actual / total_target, where totals are from Q3+Q4.
 * - If Q3/Q4 target-actual values are not provided, progress% may be used as fallback.
 * - Quality (Q), Efficiency (E), and Timeliness (T) default from N using fixed thresholds but may be overridden by the rater.
 * - Average R = (Q + E + T) / 3.
 * - Weighted score (Remarks column) = Average × (Weight% ÷ 100).
 * - TOTAL row Remarks = sum of weighted scores; FINAL AVERAGE RATING = same total.
 */
final class IpcrFormRatingCalculator
{
    /**
     * @return array{target_total: ?float, actual_total: ?float, percent: ?float}
     */
    public static function totalsFromQ3Q4(
        ?float $q3Target,
        ?float $q3Actual,
        ?float $q4Target,
        ?float $q4Actual,
    ): array {
        if ($q3Target === null && $q3Actual === null && $q4Target === null && $q4Actual === null) {
            return [
                'target_total' => null,
                'actual_total' => null,
                'percent' => null,
            ];
        }

        $targetTotal = max(0.0, ($q3Target ?? 0.0) + ($q4Target ?? 0.0));
        $actualTotal = max(0.0, ($q3Actual ?? 0.0) + ($q4Actual ?? 0.0));
        $percent = $targetTotal > 0 ? ($actualTotal / $targetTotal) : null;

        return [
            'target_total' => round($targetTotal, 4),
            'actual_total' => round($actualTotal, 4),
            'percent' => $percent !== null ? round($percent, 6) : null,
        ];
    }

    public static function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public static function nullableWholeNumber(mixed $value): ?float
    {
        $decimal = self::nullableDecimal($value);

        return $decimal !== null ? (float) round($decimal) : null;
    }

    public static function accomplishmentRatio(?float $actual, ?float $target, int $progress): float
    {
        if ($actual !== null && $target !== null && (float) $target > 0) {
            return max(0.0, (float) $actual / (float) $target);
        }

        return max(0.0, min(5.0, $progress / 100.0));
    }

    public static function qualityFromAccomplishmentRatio(float $ratio): int
    {
        if ($ratio >= 1.30) {
            return 5;
        }
        if ($ratio >= 1.15) {
            return 4;
        }
        if ($ratio >= 1.00) {
            return 3;
        }
        if ($ratio >= 0.51) {
            return 2;
        }

        return 1;
    }

    /**
     * @return array{quality: ?int, efficiency: ?int, timeliness: ?int, average: ?float, weighted: ?float}
     */
    public static function scoreRowFromRatings(
        int $quality,
        int $efficiency,
        int $timeliness,
        ?float $weightPercent,
    ): array {
        if ($weightPercent === null) {
            return [
                'quality' => null,
                'efficiency' => null,
                'timeliness' => null,
                'average' => null,
                'weighted' => null,
            ];
        }

        $average = ($quality + $efficiency + $timeliness) / 3.0;

        return [
            'quality' => $quality,
            'efficiency' => $efficiency,
            'timeliness' => $timeliness,
            'average' => round($average, 4),
            'weighted' => self::weightedFromAverageAndWeight($average, $weightPercent),
        ];
    }

    /**
     * @return array{quality: int, efficiency: int, timeliness: int, average: float, weighted: float}
     */
    public static function scoreRowFromAccomplishment(float $weightPercent, float $accomplishmentRatio): array
    {
        $rating = self::qualityFromAccomplishmentRatio($accomplishmentRatio);

        return [
            ...self::scoreRow($rating, $rating, $weightPercent, $accomplishmentRatio, $rating),
            'efficiency' => $rating,
            'timeliness' => $rating,
        ];
    }

    /**
     * @return array{quality: int, average: float, weighted: float}
     */
    public static function scoreRow(
        int $efficiency,
        int $timeliness,
        float $weightPercent,
        float $accomplishmentRatio,
        ?int $quality = null,
    ): array {
        $quality = $quality ?? self::qualityFromAccomplishmentRatio($accomplishmentRatio);
        $average = ($quality + $efficiency + $timeliness) / 3.0;
        $weighted = self::weightedFromAverageAndWeight($average, $weightPercent);

        return [
            'quality' => $quality,
            'average' => round($average, 4),
            'weighted' => $weighted,
        ];
    }

    /**
     * Persist accomplishment numbers and auto-computed (or provided) ratings onto a commitment.
     *
     * @param  array<string, mixed>  $row
     */
    public static function applyRowRatings(\App\Models\Commitment $commitment, array $row, bool $autoRatings = true): void
    {
        $q3Target = self::nullableWholeNumber($row['rating_q3_target'] ?? null);
        $q3Actual = self::nullableWholeNumber($row['rating_q3_actual'] ?? null);
        $q4Target = self::nullableWholeNumber($row['rating_q4_target'] ?? null);
        $q4Actual = self::nullableWholeNumber($row['rating_q4_actual'] ?? null);

        $totals = self::totalsFromQ3Q4($q3Target, $q3Actual, $q4Target, $q4Actual);
        $weight = $commitment->weight !== null ? (float) $commitment->weight : null;

        $payload = [
            'rating_q3_target' => $q3Target,
            'rating_q3_actual' => $q3Actual,
            'rating_q4_target' => $q4Target,
            'rating_q4_actual' => $q4Actual,
            'rating_target_total' => $totals['target_total'],
            'rating_actual_total' => $totals['actual_total'],
            'rating_percent' => $totals['percent'],
            'remarks' => null,
        ];

        if ($weight === null) {
            $commitment->update([
                ...$payload,
                'rating_quality' => null,
                'rating_efficiency' => null,
                'rating_timeliness' => null,
                'rating_average' => null,
                'rating_weighted' => null,
            ]);

            return;
        }

        $quality = isset($row['rating_quality']) && is_numeric($row['rating_quality'])
            ? (int) $row['rating_quality']
            : null;
        $efficiency = isset($row['rating_efficiency']) && is_numeric($row['rating_efficiency'])
            ? (int) $row['rating_efficiency']
            : null;
        $timeliness = isset($row['rating_timeliness']) && is_numeric($row['rating_timeliness'])
            ? (int) $row['rating_timeliness']
            : null;

        if ($autoRatings || $quality === null || $efficiency === null || $timeliness === null) {
            $ratio = $totals['percent'] ?? 0.0;
            $suggested = $totals['percent'] !== null
                ? self::qualityFromAccomplishmentRatio($ratio)
                : null;

            $quality = $quality ?? $suggested;
            $efficiency = $efficiency ?? $suggested;
            $timeliness = $timeliness ?? $suggested;
        }

        if ($quality === null || $efficiency === null || $timeliness === null) {
            $commitment->update([
                ...$payload,
                'rating_quality' => null,
                'rating_efficiency' => null,
                'rating_timeliness' => null,
                'rating_average' => null,
                'rating_weighted' => null,
            ]);

            return;
        }

        $scored = self::scoreRowFromRatings($quality, $efficiency, $timeliness, $weight);

        $commitment->update([
            ...$payload,
            'rating_quality' => $scored['quality'],
            'rating_efficiency' => $scored['efficiency'],
            'rating_timeliness' => $scored['timeliness'],
            'rating_average' => $scored['average'],
            'rating_weighted' => $scored['weighted'],
            'remarks' => $scored['weighted'] !== null
                ? number_format((float) $scored['weighted'], 2, '.', '')
                : null,
        ]);
    }

    public static function weightedFromAverageAndWeight(float $average, ?float $weightPercent): ?float
    {
        if ($weightPercent === null) {
            return null;
        }

        $w = max(0.0, $weightPercent / 100.0);

        return round($average * $w, 4);
    }
}
