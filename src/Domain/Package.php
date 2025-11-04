<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Represents a package to be delivered.
 */
final class Package
{
    public function __construct(
        public readonly string $id,
        public readonly float $weightKg,
        public readonly float $distanceKm,
        public readonly ?string $offerCode, // may be null or "NA"
    ) {
    }
}
