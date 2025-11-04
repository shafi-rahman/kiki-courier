<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Package;
use App\Domain\Vehicle;
use App\Domain\Shipment;
use App\Support\MinHeap;
use App\Support\ShipmentSelectorInterface;
use App\Support\AdaptiveShipmentSelector;

/**
 * Schedules package deliveries across multiple vehicles.
 * Calculates estimated delivery times (ETAs) for each package.
 */
final class DeliveryScheduler
{
    private ShipmentSelectorInterface $selector;

    /**
     * Create a scheduler with optional custom selector strategy.
     *
     * @param ShipmentSelectorInterface|null $selector Custom selector (defaults to adaptive)
     */
    public function __construct(?ShipmentSelectorInterface $selector = null)
    {
        $this->selector = $selector ?? new AdaptiveShipmentSelector();
    }

    /**
     * Compute per-package ETA (in hours) following the SRS rules.
     *
     * @param Package[] $packages Packages to deliver
     * @param Vehicle[] $vehicles Vehicles available for delivery
     * @return array<string,float> Map: pkgId => etaHours
     */
    public function estimateTimes(array $packages, array $vehicles): array
    {
        // Copy to a map for quick removal
        $remaining = [];
        foreach ($packages as $p) {
            $remaining[$p->id] = $p;
        }

        $heap = new MinHeap();
        foreach ($vehicles as $v) {
            // Use vehicle ID as secondary tie-breaker for stability
            $heap->push($v, $v->availableAt, $v->id);
        }

        $eta = []; // pkgId => etaHours

        while (!empty($remaining)) {
            /** @var Vehicle $veh */
            $veh = $heap->pop();
            $currentTime = $veh->availableAt;

            // pick best shipment under capacity
            $shipment = $this->selector->selectShipment(
                array_values($remaining),
                $veh->maxCarriableWeightKg
            );

            // deliver all in shipment
            $farthestOneWay = 0.0;

            foreach ($shipment->packages as $pkg) {
                // 1) one-way, truncated to 2 decimals
                $oneWay = $this->trunc2($pkg->distanceKm / $veh->maxSpeedKmph);

                // 2) arrival time = current + oneWay (truncated)
                $arrival = $this->trunc2($currentTime + $oneWay);
                $eta[$pkg->id] = $arrival;

                // remove from pool
                unset($remaining[$pkg->id]);

                // 3) farthest one-way for THIS dispatch uses the truncated one-way
                if ($oneWay > $farthestOneWay) {
                    $farthestOneWay = $oneWay;
                }
            }

            // vehicle available after truncated round trip of farthest
            $veh->availableAt = $this->trunc2($currentTime + 2 * $farthestOneWay);
            $heap->push($veh, $veh->availableAt, $veh->id);
        }

        return $eta;
    }

    /**
     * Truncate a float value to 2 decimals (not round).
     * Resilient to floating-point artifacts.
     *
     * @param float $v Value to truncate
     * @return float Truncated value
     */
    private function trunc2(float $v): float
    {
        return floor(($v + 1e-9) * 100) / 100.0;
    }
}
