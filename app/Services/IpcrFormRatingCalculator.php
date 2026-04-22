<?php

namespace App\Services;

/**
 * IPCR Form 1 style ratings (CHED sample workbook):
 * - Accomplishment ratio N = total_actual / total_target, where totals are from Q3+Q4.
 * - If Q3/Q4 target-actual values are not provided, progress% may be used as fallback.
 * - Quality (Q) is derived from N using fixed thresholds (130%, 115%, 100%, 51%).
 * - Efficiency (E) and Timeliness (T) are entered by the rater (1–5).
 * - Average R = (Q + E + T) / 3.
 * - Weighted score ("Remarks" column in sample) S = R × (row_weight_as_fraction), weight% / 100.
 * - Overall package score = sum(S) when commitment weights sum to 100%.
 */
final class IpcrFormRatingCalculator
{
    /**
     * @return array{target_total: float, actual_total: float, percent: float}
     */
    public static function totalsFromQ3Q4(float $q3Target, float $q3Actual, float $q4Target, float $q4Actual): array
    {
        $targetTotal = max(0.0, $q3Target + $q4Target);
        $actualTotal = max(0.0, $q3Actual + $q4Actual);
        $percent = $targetTotal > 0 ? ($actualTotal / $targetTotal) : 0.0;

        return [
            'target_total' => round($targetTotal, 4),
            'actual_total' => round($actualTotal, 4),
            'percent' => round($percent, 6),
        ];
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
     * @return array{quality: int, average: float, weighted: float}
     */
    public static function scoreRow(
        int $efficiency,
        int $timeliness,
        float $weightPercent,
        float $accomplishmentRatio,
    ): array {
        $quality = self::qualityFromAccomplishmentRatio($accomplishmentRatio);
        $average = ($quality + $efficiency + $timeliness) / 3.0;
        $w = max(0.0, $weightPercent / 100.0);
        $weighted = round($average * $w, 6);

        return [
            'quality' => $quality,
            'average' => round($average, 4),
            'weighted' => $weighted,
        ];
    }
}
