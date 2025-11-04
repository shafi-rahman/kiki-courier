<?php

declare(strict_types=1);

namespace App\Offers;

use App\Domain\Package;

/**
 * Offer rule based on distance and weight ranges.
 * Supports both inclusive and exclusive boundaries.
 */
final class RangeOffer implements OfferRuleInterface
{
    public function __construct(
        private readonly string $code,
        private readonly float $percent,            // e.g. 0.10
        private readonly ?float $minDistanceKm = null, // inclusive; null = no min
        private readonly ?float $maxDistanceKm = null, // inclusive/exclusive; null = no max
        private readonly ?float $minWeightKg = null,   // inclusive; null = no min
        private readonly ?float $maxWeightKg = null,   // inclusive; null = no max
        private readonly string $distanceMaxType = 'inclusive_max', // 'exclusive_max' or 'inclusive_max'
        private ?array $distance = null, // ['min','min_inclusive','max','max_inclusive']
        private ?array $weight = null
    ) {
    }

    /**
     * Get the offer code.
     *
     * @return string The offer code
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Match a package against this offer's criteria.
     *
     * @param Package $p Package to match
     * @return float|null Discount percent if matched, 0.0 if criteria not met, null if code doesn't match
     */
    public function match(Package $p): ?float
    {
        // offer code must match exactly
        if (!$p->offerCode || strtoupper($p->offerCode) !== strtoupper($this->code)) {
            return null;
        }
        $d = $p->distanceKm;
        $w = $p->weightKg;

        // Check distance boundaries
        if ($this->minDistanceKm !== null && $d < $this->minDistanceKm) {
            return 0.0;
        }
        if ($this->maxDistanceKm !== null) {
            if ($this->distanceMaxType === 'exclusive_max') {
                if ($d >= $this->maxDistanceKm) {
                    return 0.0;
                }
            } else {
                if ($d > $this->maxDistanceKm) {
                    return 0.0;
                }
            }
        }

        // Check weight boundaries
        if ($this->minWeightKg !== null && $w < $this->minWeightKg) {
            return 0.0;
        }
        if ($this->maxWeightKg !== null && $w > $this->maxWeightKg) {
            return 0.0;
        }

        return $this->percent;
    }

    private function inRange(float $val, ?array $spec): bool
    {
        if ($spec === null) {
            return true;
        }
        if (array_key_exists('min', $spec)) {
            $ok = !empty($spec['min_inclusive']) ? ($val >= (float)$spec['min']) : ($val > (float)$spec['min']);
            if (!$ok) {
                return false;
            }
        }
        if (array_key_exists('max', $spec)) {
            $ok = !empty($spec['max_inclusive']) ? ($val <= (float)$spec['max']) : ($val < (float)$spec['max']);
            if (!$ok) {
                return false;
            }
        }
        return true;
    }
}
