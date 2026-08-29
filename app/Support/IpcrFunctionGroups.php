<?php

namespace App\Support;

use App\Models\Commitment;
use Illuminate\Support\Collection;

final class IpcrFunctionGroups
{
    /**
     * @param  iterable<int, Commitment>  $commitments
     * @return list<list<Commitment>>
     */
    public static function consecutive(iterable $commitments): array
    {
        $groups = [];
        $current = [];
        $key = null;

        foreach ($commitments as $commitment) {
            $next = ($commitment->function_type ?? 'core').'|'.(int) ($commitment->function_group ?? 0);
            if ($current !== [] && $next !== $key) {
                $groups[] = $current;
                $current = [];
            }
            $current[] = $commitment;
            $key = $next;
        }

        if ($current !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     * @return Collection<int, Commitment>
     */
    public static function ordered(Collection $commitments): Collection
    {
        return $commitments->sortBy([
            fn (Commitment $c) => $c->function_type === 'core' ? 0 : 1,
            fn (Commitment $c) => (int) ($c->sort_order ?? 0),
            fn (Commitment $c) => (int) $c->id,
        ])->values();
    }
}
