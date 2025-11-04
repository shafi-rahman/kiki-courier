<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Package;
use App\Domain\Shipment;

/**
 * Interface for different shipment selection strategies.
 * Allows swapping algorithms without changing the scheduler.
 */
interface ShipmentSelectorInterface
{
    /**
     * Select best shipment from candidates under weight constraint.
     *
     * @param Package[] $candidates Available packages
     * @param float $maxWeight Maximum weight constraint
     * @return Shipment Selected shipment
     */
    public function selectShipment(array $candidates, float $maxWeight): Shipment;
}
