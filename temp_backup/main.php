<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Domain\Package;
use App\Domain\Vehicle;
use App\Offers\OfferRegistry;
use App\Offers\RangeOffer;
use App\Offers\NoOffer;
use App\Services\CostCalculator;
use App\Services\DeliveryScheduler;

/**
 * Read stdin fully once, trim, ignore empties.
 */
$lines = [];
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line !== '') $lines[] = $line;
}
if (empty($lines)) {
    fwrite(STDERR, "No input provided.\n");
    exit(1);
}

/**
 * 1) First line: "<base_delivery_cost> <no_of_packages>"
 */
$parts = preg_split('/\s+/', $lines[0]);
if (count($parts) !== 2 || !is_numeric($parts[0]) || !ctype_digit($parts[1])) {
    fwrite(STDERR, "Invalid first line. Expected: <base_delivery_cost> <no_of_packages>\n");
    exit(1);
}
$baseCost = (float)$parts[0];
$n        = (int)$parts[1];

/**
 * 2) Next n lines: packages
 */
if (count($lines) < ($n + 1)) {
    fwrite(STDERR, "Expected $n package lines but input ended early.\n");
    exit(1);
}

$packages = [];
for ($i = 1; $i <= $n; $i++) {
    $p = preg_split('/\s+/', $lines[$i]);
    if (count($p) !== 4 || !is_numeric($p[1]) || !is_numeric($p[2])) {
        fwrite(STDERR, "Invalid package line $i. Expected: <pkg_id> <weight_kg> <distance_km> <offer_code>\n");
        exit(1);
    }
    $id     = (string)$p[0];
    $weight = (float)$p[1];
    $dist   = (float)$p[2];
    $offer  = strtoupper((string)$p[3]);
    if ($offer === 'NA') $offer = null;

    $packages[] = new Package($id, $weight, $dist, $offer);
}

/**
 * 3) Optional scheduling line:
 *    "<number_of_vehicles> <max_speed_kmph> <max_carriable_weight_kg>"
 */
$hasScheduling = count($lines) > ($n + 1);
$vehicles = [];
if ($hasScheduling) {
    $schedParts = preg_split('/\s+/', $lines[$n + 1]);
    if (count($schedParts) !== 3 || !ctype_digit($schedParts[0]) || !is_numeric($schedParts[1]) || !is_numeric($schedParts[2])) {
        fwrite(STDERR, "Invalid vehicle line. Expected: <number_of_vehicles> <max_speed_kmph> <max_carriable_weight_kg>\n");
        exit(1);
    }

    $vehicleCount = (int)$schedParts[0];
    $speed        = (float)$schedParts[1];
    $capacity     = (float)$schedParts[2];

    // Capacity sanity check: no single package should exceed capacity
    foreach ($packages as $pkg) {
        if ($pkg->weightKg > $capacity) {
            fwrite(STDERR, "Package {$pkg->id} (weight {$pkg->weightKg}) exceeds vehicle capacity {$capacity}.\n");
            exit(1);
        }
    }

    for ($i = 1; $i <= $vehicleCount; $i++) {
        $vehicles[] = new Vehicle($i, $speed, $capacity);
    }
}

/**
 * 4) Offers registry (easy to extend)
 */
$registry = new OfferRegistry(
    new NoOffer(),
    new RangeOffer('OFR001', 0.10, null, 199.9999,  70, 200),   // dist < 200,  weight 70–200
    new RangeOffer('OFR002', 0.07, 50,   150,       100, 250),  // dist 50–150, weight 100–250
    new RangeOffer('OFR003', 0.05, 50,   250,        10, 150)   // dist 50–250, weight 10–150
);

/**
 * 5) Compute costs
 */
$costCalc = new CostCalculator($baseCost, $registry);
$rows = []; // for printing later
foreach ($packages as $p) {
    $c = $costCalc->costFor($p);
    $rows[$p->id] = [
        'id'       => $p->id,
        'discount' => $c['discount'],
        'total'    => $c['total'],
        'eta'      => null,
    ];
}

/**
 * 6) If scheduling info present, compute ETAs
 */
if ($hasScheduling) {
    $scheduler = new DeliveryScheduler();
    $etas = $scheduler->estimateTimes($packages, $vehicles);
    foreach ($etas as $pkgId => $eta) {
        $rows[$pkgId]['eta'] = $eta;
    }
}

/**
 * 7) Print output in required format
 */
foreach ($packages as $p) {
    $r = $rows[$p->id];
    if ($hasScheduling) {
        printf(
            "%s %s %s %s\n",
            $r['id'],
            rtrim(rtrim(number_format($r['discount'], 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($r['total'],    2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($r['eta'],      2, '.', ''), '0'), '.')
        );
    } else {
        printf(
            "%s %s %s\n",
            $r['id'],
            rtrim(rtrim(number_format($r['discount'], 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($r['total'],    2, '.', ''), '0'), '.')
        );
    }
}
