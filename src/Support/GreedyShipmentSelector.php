<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Package;
use App\Domain\Shipment;

/**
 * Greedy shipment selector: O(n log n) instead of exponential.
 *
 * Algorithm:
 * 1. Start with empty shipment
 * 2. Repeatedly try to add the next lightest available package
 * 3. If it fits, add it; otherwise skip to next candidate
 * 4. Return the greedy shipment
 *
 * This respects SRS tie-break rules through deterministic ordering
 * and is practical for large inputs (n > 25).
 */
final class GreedyShipmentSelector implements ShipmentSelectorInterface
{
    /**
     * Select shipment using greedy approach.
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

        // Sort by weight ascending (lightest first)
        $sorted = $candidates;
        usort($sorted, static fn (Package $a, Package $b): int => $a->weightKg <=> $b->weightKg);

        $selected = [];
        $currentWeight = 0.0;

        foreach ($sorted as $pkg) {
            $newWeight = $currentWeight + $pkg->weightKg;
            if ($newWeight <= $maxWeight) {
                $selected[] = $pkg;
                $currentWeight = $newWeight;
            }
        }

        // If nothing selected (shouldn't happen if inputs valid), pick lightest
        if (empty($selected)) {
            $selected = [$sorted[0]];
        }

        return new Shipment($selected);
    }
}
