<?php
namespace App\Services;

use App\Domain\Package;
use App\Offers\OfferRegistry;

final class CostCalculator
{
    public function __construct(
        private float $baseDeliveryCost,
        private OfferRegistry $offers
    ) {}

    /** @return array{discount: float, total: float} */
    public function costFor(Package $p): array
    {
        $raw = $this->baseDeliveryCost + ($p->weightKg * 10) + ($p->distanceKm * 5);
        $pct = $this->offers->resolveDiscountPercent($p);
        $discount = round($raw * $pct, 2);
        $total    = round($raw - $discount, 2);

        return ['discount' => $discount, 'total' => $total];
    }
}
