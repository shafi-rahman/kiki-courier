<?php
namespace App\Services;

use App\Domain\Package;
use App\Domain\Vehicle;
use App\Domain\Shipment;
use App\Support\MinHeap;
use App\Support\Combinatorics;

final class DeliveryScheduler
{
    /**
     * Compute per-package ETA (in hours) following the SRS rules.
     *
     * @param Package[] $packages
     * @param Vehicle[] $vehicles
     * @return array<string,float> map: pkgId => etaHours
     */
    public function estimateTimes(array $packages, array $vehicles): array
    {
        // Copy to a map for quick removal
        $remaining = [];
        foreach ($packages as $p) $remaining[$p->id] = $p;

        $heap = new MinHeap();
        foreach ($vehicles as $v) $heap->push($v, $v->availableAt);

        $eta = []; // pkgId => etaHours

        while (!empty($remaining)) {
            /** @var Vehicle $veh */
            $veh = $heap->pop();
            $currentTime = $veh->availableAt;

            // pick best shipment under capacity
            $best = $this->selectShipment(array_values($remaining), $veh->maxCarriableWeightKg);

            // deliver all in shipment
            $farthestOneWay = 0.0;

            foreach ($best->packages as $pkg) {
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
            $heap->push($veh, $veh->availableAt);
        }

        return $eta;
    }

    /**
     * Choose a subset that:
     *  1) maximizes #packages,
     *  2) if tie, maximizes total weight,
     *  3) if tie, minimizes farthest distance.
     *
     * @param Package[] $candidates
     */
    private function selectShipment(array $candidates, float $maxWeight): Shipment
    {
        $best = null;

        foreach (Combinatorics::allSubsets($candidates) as $subset) {
            $totalW = array_reduce($subset, fn($c, $p) => $c + $p->weightKg, 0.0);
            if ($totalW > $maxWeight) continue;

            $count = count($subset);
            $farthest = 0.0;
            foreach ($subset as $p) $farthest = max($farthest, $p->distanceKm);

            if ($best === null) {
                $best = ['subset' => $subset, 'count' => $count, 'weight' => $totalW, 'far' => $farthest];
                continue;
            }

            // rule 1: more packages
            if ($count > $best['count']) {
                $best = ['subset' => $subset, 'count' => $count, 'weight' => $totalW, 'far' => $farthest];
                continue;
            }
            if ($count < $best['count']) continue;

            // rule 2: heavier
            if ($totalW > $best['weight']) {
                $best = ['subset' => $subset, 'count' => $count, 'weight' => $totalW, 'far' => $farthest];
                continue;
            }
            if ($totalW < $best['weight']) continue;

            // rule 3: earliest delivery -> smaller farthest distance
            if ($farthest < $best['far']) {
                $best = ['subset' => $subset, 'count' => $count, 'weight' => $totalW, 'far' => $farthest];
            }
        }

        // Fallback: in pathological case if nothing fits (shouldn’t happen if inputs valid),
        // pick the single lightest package.
        if ($best === null) {
            usort($candidates, fn($a,$b)=>$a->weightKg <=> $b->weightKg);
            $best = ['subset'=>[$candidates[0]], 'count'=>1, 'weight'=>$candidates[0]->weightKg, 'far'=>$candidates[0]->distanceKm];
        }

        return new Shipment($best['subset']);
    }

    // helper: truncate to 2 decimals (not round), resilient to float artifacts
    private function trunc2(float $v): float {
        return floor(($v + 1e-9) * 100) / 100.0;
    }
}
