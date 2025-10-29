<?php
namespace App\Offers;

use App\Domain\Package;

final class NoOffer implements OfferRuleInterface
{
    public function code(): string { return 'NA'; }

    public function match(Package $p): ?float
    {
        if ($p->offerCode === null || strtoupper($p->offerCode) === 'NA') {
            return 0.0;
        }
        return null;
    }
}
