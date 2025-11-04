<?php

declare(strict_types=1);

namespace App\Offers;

use App\Domain\Package;

/**
 * Registry for managing multiple offer rules.
 * Routes packages to appropriate offers and resolves discounts.
 */
final class OfferRegistry
{
    /**
     * @var OfferRuleInterface[]
     */
    private array $rules = [];

    /**
     * Create a registry with initial rules.
     *
     * @param OfferRuleInterface ...$rules Initial offer rules
     */
    public function __construct(OfferRuleInterface ...$rules)
    {
        $this->rules = $rules;
    }

    /**
     * Add an offer rule to the registry.
     *
     * @param OfferRuleInterface $rule The rule to add
     */
    public function add(OfferRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * Resolve discount percent for a package.
     *
     * @param Package $p Package to resolve discount for
     * @return float Discount percent (0.0 ... 1.0). Unknown/invalid codes return 0.0
     */
    public function resolveDiscountPercent(Package $p): float
    {
        foreach ($this->rules as $rule) {
            $match = $rule->match($p);
            if ($match !== null) {
                return $match; // could be 0.0 or >0
            }
        }
        // has a code but none recognizes it => invalid => 0
        return 0.0;
    }
}
