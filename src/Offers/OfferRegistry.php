<?php
namespace App\Offers;

use App\Domain\Package;

final class OfferRegistry
{
    /** @var OfferRuleInterface[] */
    private array $rules = [];

    public function __construct(OfferRuleInterface ...$rules) {
        $this->rules = $rules;
    }

    public function add(OfferRuleInterface $rule): void {
        $this->rules[] = $rule;
    }

    /** Returns discount percent (0..1). Unknown/invalid -> 0 per SRS. */
    public function resolveDiscountPercent(Package $p): float
    {
        foreach ($this->rules as $rule) {
            $match = $rule->match($p);
            if ($match !== null) return $match; // could be 0.0 or >0
        }
        // has a code but none recognizes it => invalid => 0
        return 0.0;
    }
}
