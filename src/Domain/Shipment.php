<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Represents a group of packages dispatched together in a single shipment.
 */
final class Shipment
{
    /**
     * @param Package[] $packages List of packages in this shipment
     */
    public function __construct(
        public readonly array $packages,
    ) {
    }

    /**
     * Calculate the total weight of all packages in the shipment.
     *
     * @return float Total weight in kg
     */
    public function totalWeight(): float
    {
        return array_reduce(
            $this->packages,
            static fn (float $carry, Package $p): float => $carry + $p->weightKg,
            0.0
        );
    }

    /**
     * Get the farthest distance among all packages in the shipment.
     *
     * @return float Maximum distance in km
     */
    public function farthestDistance(): float
    {
        $max = 0.0;
        foreach ($this->packages as $p) {
            if ($p->distanceKm > $max) {
                $max = $p->distanceKm;
            }
        }
        return $max;
    }
}
