<?php

declare(strict_types=1);

namespace App\Offers;

use App\Domain\Package;

/**
 * Interface for offer rules that can match packages and provide discounts.
 */
interface OfferRuleInterface
{
    /**
     * Check if this offer matches the package and return discount percent.
     *
     * @param Package $p Package to check
     * @return float|null Discount percent (0.0 ... 1.0), or null if rule does not match
     */
    public function match(Package $p): ?float;

    /**
     * Get the offer code.
     *
     * @return string The offer code (e.g., "OFR001")
     */
    public function code(): string;
}
