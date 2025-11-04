<?php

declare(strict_types=1);

namespace App\Offers;

use App\Domain\Package;

/**
 * Default offer rule for packages with no offer code or "NA" code.
 */
final class NoOffer implements OfferRuleInterface
{
    /**
     * Get the offer code.
     *
     * @return string Always returns 'NA'
     */
    public function code(): string
    {
        return 'NA';
    }

    /**
     * Match packages with null or "NA" offer code.
     *
     * @param Package $p Package to check
     * @return float|null Returns 0.0 if no offer or NA, null otherwise
     */
    public function match(Package $p): ?float
    {
        if ($p->offerCode === null || strtoupper($p->offerCode) === 'NA') {
            return 0.0;
        }
        return null;
    }
}
