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

// Simple CLI flags
$cliVerbose = false;
$offersConfig = null;
foreach ($argv as $a) {
    if ($a === '--verbose' || $a === '-v') $cliVerbose = true;
    if ($a === '--help' || $a === '-h') {
        fwrite(STDOUT, "Usage: php main.php [--help] [--verbose] [--offers=path] < input.txt\n");
        exit(0);
    }
    if (str_starts_with($a, '--offers=')) {
        $offersConfig = substr($a, strlen('--offers='));
    }
}

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

// basic validation
if ($baseCost < 0) {
    fwrite(STDERR, "Base delivery cost must be non-negative.\n");
    exit(1);
}
if ($n < 0) {
    fwrite(STDERR, "Number of packages must be non-negative.\n");
    exit(1);
}

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

    if ($weight < 0 || $dist < 0) {
        fwrite(STDERR, "Invalid numeric values for package $id. Weight and distance must be non-negative.\n");
        exit(1);
    }

    // uniqueness check
    foreach ($packages as $existing) {
        if ($existing->id === $id) {
            fwrite(STDERR, "Duplicate package id: $id\n");
            exit(1);
        }
    }

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
 * 4) Offers registry (supports optional JSON config passed via --offers=path)
 */
$offers = [ new NoOffer() ];
if ($offersConfig !== null) {
    if (!file_exists($offersConfig) || !is_readable($offersConfig)) {
        fwrite(STDERR, "Offers config not found or unreadable: $offersConfig\n");
        exit(1);
    }
    $json = file_get_contents($offersConfig);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid offers config: must be a JSON array\n");
        exit(1);
    }
    foreach ($data as $entry) {
        if (!isset($entry['code'],$entry['discount'])) continue;
        $minD = $entry['minDistance'] ?? null;
        $maxD = $entry['maxDistance'] ?? null;
        $minW = $entry['minWeight'] ?? null;
        $maxW = $entry['maxWeight'] ?? null;
        $offers[] = new RangeOffer(
            $entry['code'], (float)$entry['discount'], $minD, $maxD, $minW, $maxW
        );
    }
    if ($cliVerbose) fwrite(STDERR, "Loaded " . (count($offers)-1) . " offers from $offersConfig\n");
} else {
    // default offers
    $offers[] = new RangeOffer('OFR001', 0.10, null, 199.9999,  70, 200);
    $offers[] = new RangeOffer('OFR002', 0.07, 50,   150,       100, 250);
    $offers[] = new RangeOffer('OFR003', 0.05, 50,   250,        10, 150);
}

$registry = new OfferRegistry(...$offers);

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
