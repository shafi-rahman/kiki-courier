<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Package;
use App\Domain\Shipment;

/**
 * Adaptive shipment selector that chooses the best algorithm based on input size.
 *
 * Strategy:
 * - Small inputs (n < 20): Use exhaustive enumeration for optimal results
 * - Large inputs (n >= 20): Use greedy for practical performance
 *
 * This combines correctness (optimal results for small inputs) with
 * performance (linear time for large inputs).
 */
final class AdaptiveShipmentSelector implements ShipmentSelectorInterface
{
    private const THRESHOLD = 20;

    private ExhaustiveShipmentSelector $exhaustive;
    private GreedyShipmentSelector $greedy;

    public function __construct()
    {
        $this->exhaustive = new ExhaustiveShipmentSelector();
        $this->greedy = new GreedyShipmentSelector();
    }

    /**
     * Select shipment using the best strategy for the input size.
     *
     * @param Package[] $candidates Available packages
     * @param float $maxWeight Maximum weight constraint
     * @return Shipment Selected shipment
     */
    public function selectShipment(array $candidates, float $maxWeight): Shipment
    {
        if (count($candidates) < self::THRESHOLD) {
            return $this->exhaustive->selectShipment($candidates, $maxWeight);
        }
        return $this->greedy->selectShipment($candidates, $maxWeight);
    }
}
