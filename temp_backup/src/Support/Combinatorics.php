<?php
namespace App\Support;

final class Combinatorics
{
    /**
     * @template T
     * @param array<int,T> $items
     * @return iterable<array<int,T>>
     */
    public static function allSubsets(array $items): iterable
    {
        $n = count($items);
        $limit = 1 << $n;
        for ($mask = 1; $mask < $limit; $mask++) { // non-empty only
            $subset = [];
            for ($i = 0; $i < $n; $i++) {
                if ($mask & (1 << $i)) $subset[] = $items[$i];
            }
            yield $subset;
        }
    }
}
