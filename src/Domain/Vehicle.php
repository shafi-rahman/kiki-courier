<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Represents a delivery vehicle with capacity and speed constraints.
 */
final class Vehicle
{
    /** When is this vehicle free again? (in hours from t0) */
    public float $availableAt = 0.0;

    public function __construct(
        public readonly int $id,
        public readonly float $maxSpeedKmph,
        public readonly float $maxCarriableWeightKg,
    ) {
    }
}
