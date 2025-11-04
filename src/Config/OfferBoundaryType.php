<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Explicit boundary semantics for offer conditions.
 * Clarifies whether boundaries are inclusive or exclusive.
 */
enum OfferBoundaryType: string
{
    /** Inclusive: value >= boundary */
    case INCLUSIVE_MIN = 'inclusive_min';

    /** Inclusive: value <= boundary */
    case INCLUSIVE_MAX = 'inclusive_max';

    /** Exclusive: value < boundary */
    case EXCLUSIVE_MAX = 'exclusive_max';

    /** Exclusive: value > boundary */
    case EXCLUSIVE_MIN = 'exclusive_min';
}
