<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Package;
use App\Offers\OfferRegistry;

/**
 * Calculates delivery cost for packages including base cost and discounts.
 */
final class CostCalculator
{
    /**
     * Create a cost calculator.
     *
     * @param float $baseDeliveryCost Base delivery cost in currency units
     * @param OfferRegistry $offers Registry of available offers
     */
    public function __construct(
        private readonly float $baseDeliveryCost,
        private readonly OfferRegistry $offers,
    ) {
    }

    /**
     * Calculate cost for a single package.
     *
     * Formula: base + (10 * weight_kg) + (5 * distance_km) - discount
     *
     * @param Package $p Package to calculate cost for
     * @return array{discount: float, total: float} Discount amount and total cost
     */
    public function costFor(Package $p): array
    {
        $raw = $this->baseDeliveryCost + ($p->weightKg * 10) + ($p->distanceKm * 5);
        $pct = $this->offers->resolveDiscountPercent($p);
        $discount = round($raw * $pct, 2);
        $total = round($raw - $discount, 2);

        return ['discount' => $discount, 'total' => $total];
    }
}
