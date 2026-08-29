<?php

namespace App\Support;

final class IpcrIncludedQuarters
{
    /**
     * @param  mixed  $value
     * @return list<int>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $unique = [];
        foreach ($value as $quarter) {
            $n = (int) $quarter;
            if ($n >= 1 && $n <= 4) {
                $unique[$n] = $n;
            }
        }

        $list = array_values($unique);
        sort($list);

        return $list;
    }

    /**
     * @return list<int>
     */
    public static function default(): array
    {
        return [3, 4];
    }

    /**
     * @param  mixed  $value
     * @return list<int>
     */
    public static function existingOrDefault(mixed $value): array
    {
        $list = self::normalize($value);

        return $list !== [] ? $list : self::default();
    }

    /**
     * @param  list<int>  $quarters
     */
    public static function periodLabel(int $year, array $quarters): string
    {
        $quarters = self::existingOrDefault($quarters);
        $parts = array_map(fn (int $q) => 'Q'.$q, $quarters);

        return implode(', ', $parts).' '.$year;
    }

    /**
     * @param  list<int>  $quarters
     */
    public static function primaryQuarter(array $quarters): int
    {
        $quarters = self::existingOrDefault($quarters);

        return (int) $quarters[array_key_last($quarters)];
    }
}
