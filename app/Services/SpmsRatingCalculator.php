<?php

namespace App\Services;

/**
 * SPMS-aligned simple aggregate: mean of Quality, Efficiency, Timeliness (1–5).
 */
final class SpmsRatingCalculator
{
    public static function overall(?int $quality, ?int $efficiency, ?int $timeliness): ?float
    {
        if ($quality === null || $efficiency === null || $timeliness === null) {
            return null;
        }

        foreach ([$quality, $efficiency, $timeliness] as $v) {
            if ($v < 1 || $v > 5) {
                return null;
            }
        }

        return round(($quality + $efficiency + $timeliness) / 3, 2);
    }
}
