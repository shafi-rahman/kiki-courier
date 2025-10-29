<?php
namespace App\Domain;

final class Shipment
{
    /** @param Package[] $packages */
    public function __construct(
        public readonly array $packages
    ) {}

    public function totalWeight(): float
    {
        return array_reduce($this->packages, fn($c, $p) => $c + $p->weightKg, 0.0);
    }

    public function farthestDistance(): float
    {
        $max = 0.0;
        foreach ($this->packages as $p) {
            if ($p->distanceKm > $max) $max = $p->distanceKm;
        }
        return $max;
    }
}
