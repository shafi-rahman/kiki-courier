<?php
namespace App\Domain;

final class Package
{
    public function __construct(
        public readonly string $id,
        public readonly float $weightKg,
        public readonly float $distanceKm,
        public readonly ?string $offerCode // may be null or "NA"
    ) {}
}
