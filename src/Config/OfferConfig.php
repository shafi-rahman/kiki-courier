<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Represents a single offer configuration with explicit boundary semantics.
 */
final class OfferConfig
{
    /**
     * @param string $code              Offer code (e.g., "OFR001")
     * @param float $discount           Discount percentage (0.0-1.0)
     * @param float|null $minDistance   Minimum distance or null
     * @param float|null $maxDistance   Maximum distance or null
     * @param string $distanceMaxType   Whether maxDistance is inclusive or exclusive
     * @param float|null $minWeight     Minimum weight or null
     * @param float|null $maxWeight     Maximum weight or null
     * @param string $weightMinType     Whether minWeight is inclusive or exclusive
     * @param string $weightMaxType     Whether maxWeight is inclusive or exclusive
     */
    public function __construct(
        public readonly string $code,
        public readonly float $discount,
        public readonly ?float $minDistance = null,
        public readonly ?float $maxDistance = null,
        public readonly string $distanceMaxType = 'inclusive_max',
        public readonly ?float $minWeight = null,
        public readonly ?float $maxWeight = null,
        public readonly string $weightMinType = 'inclusive_min',
        public readonly string $weightMaxType = 'inclusive_max',
    ) {
        if ($discount < 0.0 || $discount > 1.0) {
            throw new \InvalidArgumentException(
                "Discount must be between 0.0 and 1.0, got {$discount}"
            );
        }
    }

    /**
     * Check if a distance value satisfies the boundary condition.
     */
    public function matchesDistanceBoundary(float $distance): bool
    {
        if ($this->minDistance !== null && $distance < $this->minDistance) {
            return false;
        }
        if ($this->maxDistance !== null) {
            if ($this->distanceMaxType === 'exclusive_max') {
                return $distance < $this->maxDistance;
            }
            return $distance <= $this->maxDistance;
        }
        return true;
    }

    /**
     * Check if a weight value satisfies the boundary condition.
     */
    public function matchesWeightBoundary(float $weight): bool
    {
        if ($this->minWeight !== null) {
            if ($this->weightMinType === 'exclusive_min') {
                if ($weight <= $this->minWeight) {
                    return false;
                }
            } else {
                if ($weight < $this->minWeight) {
                    return false;
                }
            }
        }

        if ($this->maxWeight !== null) {
            if ($this->weightMaxType === 'exclusive_max') {
                return $weight < $this->maxWeight;
            }
            return $weight <= $this->maxWeight;
        }
        return true;
    }
}
