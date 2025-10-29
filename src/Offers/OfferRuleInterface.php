<?php
namespace App\Offers;

use App\Domain\Package;

interface OfferRuleInterface
{
    /** Return discount percent (0..1) if applicable, else null (meaning “not my rule”). */
    public function match(Package $p): ?float;

    /** The offer code this rule is for, e.g. "OFR001" */
    public function code(): string;
}
