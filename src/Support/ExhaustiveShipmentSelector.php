<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Package;
use App\Domain\Shipment;

/**
 * Exhaustive shipment selector: O(2^n) complexity.
 *
 * Enumerates all possible subsets and selects the best according to SRS rules:
 * 1. Maximize package count
 * 2. If tie, maximize total weight
 * 3. If tie, minimize farthest distance (earliest delivery)
 *
 * ONLY use for small inputs (n < 20). For larger inputs, use GreedyShipmentSelector.
 */
final class ExhaustiveShipmentSelector implements ShipmentSelectorInterface
{
    /**
     * Select shipment using exhaustive enumeration.
     *
     * @param Package[] $candidates Available packages
     * @param float $maxWeight Maximum weight constraint
     * @return Shipment Selected shipment
     */
    public function selectShipment(array $candidates, float $maxWeight): Shipment
    {
        if (empty($candidates)) {
            return new Shipment([]);
        }

        $best = null;

        foreach (Combinatorics::allSubsets($candidates) as $subset) {
            $totalW = array_reduce(
                $subset,
                static fn (float $carry, Package $p): float => $carry + $p->weightKg,
                0.0
            );
            if ($totalW > $maxWeight) {
                continue;
            }

            $count = count($subset);
            $farthest = 0.0;
            foreach ($subset as $p) {
                if ($p->distanceKm > $farthest) {
                    $farthest = $p->distanceKm;
                }
            }

            if ($best === null) {
                $best = [
                    'subset' => $subset,
                    'count' => $count,
                    'weight' => $totalW,
                    'far' => $farthest,
                ];
                continue;
            }

            // rule 1: more packages
            if ($count > $best['count']) {
                $best = [
                    'subset' => $subset,
                    'count' => $count,
                    'weight' => $totalW,
                    'far' => $farthest,
                ];
                continue;
            }
            if ($count < $best['count']) {
                continue;
            }

            // rule 2: heavier
            if ($totalW > $best['weight']) {
                $best = [
                    'subset' => $subset,
                    'count' => $count,
                    'weight' => $totalW,
                    'far' => $farthest,
                ];
                continue;
            }
            if ($totalW < $best['weight']) {
                continue;
            }

            // rule 3: earliest delivery -> smaller farthest distance
            if ($farthest < $best['far']) {
                $best = [
                    'subset' => $subset,
                    'count' => $count,
                    'weight' => $totalW,
                    'far' => $farthest,
                ];
            }
        }

        // Fallback: pick the single lightest package
        if ($best === null) {
            usort($candidates, static fn (Package $a, Package $b): int => $a->weightKg <=> $b->weightKg);
            $best = [
                'subset' => [$candidates[0]],
                'count' => 1,
                'weight' => $candidates[0]->weightKg,
                'far' => $candidates[0]->distanceKm,
            ];
        }

        return new Shipment($best['subset']);
    }
}
