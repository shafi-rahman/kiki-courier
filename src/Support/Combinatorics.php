<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Combinatorial utilities for subset generation.
 *
 * WARNING: Exponential complexity O(2^n). Use only for small sets (n < 20).
 * For larger sets, use GreedyShipmentSelector instead.
 */
final class Combinatorics
{
    /**
     * Generate all non-empty subsets of items.
     *
     * Complexity: O(2^n) - exponential. Use only for n < 20.
     *
     * @template T
     * @param array<int,T> $items Items to generate subsets from
     * @return iterable<array<int,T>> Iterator yielding non-empty subsets
     */
    public static function allSubsets(array $items): iterable
    {
        $n = count($items);
        $limit = 1 << $n;
        for ($mask = 1; $mask < $limit; $mask++) { // non-empty only
            $subset = [];
            for ($i = 0; $i < $n; $i++) {
                if (($mask & (1 << $i)) !== 0) {
                    $subset[] = $items[$i];
                }
            }
            yield $subset;
        }
    }
}
