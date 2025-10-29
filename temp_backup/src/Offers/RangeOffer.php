<?php
namespace App\Offers;

use App\Domain\Package;

final class RangeOffer implements OfferRuleInterface
{
    public function __construct(
        private string $code,
        private float $percent,            // e.g. 0.10
        private ?float $minDistanceKm,     // inclusive; null = no min
        private ?float $maxDistanceKm,     // inclusive; null = no max
        private ?float $minWeightKg,       // inclusive; null = no min
        private ?float $maxWeightKg        // inclusive; null = no max
    ) {}

    public function code(): string { return $this->code; }

    public function match(Package $p): ?float
    {
        // offer code must match exactly
        if (!$p->offerCode || strtoupper($p->offerCode) !== strtoupper($this->code)) {
            return null;
        }
        $d = $p->distanceKm;
        $w = $p->weightKg;

        if (
            ($this->minDistanceKm === null || $d >= $this->minDistanceKm) &&
            ($this->maxDistanceKm === null || $d <= $this->maxDistanceKm) &&
            ($this->minWeightKg   === null || $w >= $this->minWeightKg)   &&
            ($this->maxWeightKg   === null || $w <= $this->maxWeightKg)
        ) {
            return $this->percent;
        }
        // code present but criteria not met => effectively 0% discount per SRS
        return 0.0;
    }
}
