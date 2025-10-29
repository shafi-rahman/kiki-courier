<?php
namespace App\Domain;

final class Vehicle
{
    public function __construct(
        public readonly int $id,
        public readonly float $maxSpeedKmph,
        public readonly float $maxCarriableWeightKg
    ) {}

    /** When is this vehicle free again? (in hours from t0) */
    public float $availableAt = 0.0;
}
