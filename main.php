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
use App\Config\ConfigLoader;
use App\UI\InputFormatter;
use App\UI\OutputFormatter;

// ============================================================================
// CLI ARGUMENT PARSING
// ============================================================================

$cliVerbose = false;
$offersConfig = null;
$outputFormat = 'human'; // 'human', 'json', 'raw'
$quiet = false;
$interactive = true; // Assume interactive unless piped

// Check if stdin is piped
if (!posix_isatty(STDIN)) {
    $interactive = false;
}

foreach ($argv as $a) {
    if ($a === '--verbose' || $a === '-v') {
        $cliVerbose = true;
    }
    if ($a === '--help' || $a === '-h') {
        fwrite(
            STDOUT,
            "Usage: php main.php [OPTIONS] < input.txt\n\n"
            . "OPTIONS:\n"
            . "  --offers=PATH       Load offers config from JSON file\n"
            . "  --format=FORMAT     Output format: human (default), json, raw\n"
            . "  --verbose, -v       Verbose output with processing details\n"
            . "  --quiet, -q         Minimal output (no headers or summary)\n"
            . "  --help, -h          Show this help message\n\n"
            . "EXAMPLES:\n"
            . "  cat input.txt | php main.php\n"
            . "  cat input.txt | php main.php --offers=config/offers.json\n"
            . "  cat input.txt | php main.php --format=json\n"
        );
        exit(0);
    }
    if (str_starts_with($a, '--offers=')) {
        $offersConfig = substr($a, strlen('--offers='));
    }
    if (str_starts_with($a, '--format=')) {
        $outputFormat = substr($a, strlen('--format='));
        if (!in_array($outputFormat, ['human', 'json', 'raw'])) {
            fwrite(STDERR, "Invalid format. Use: human, json, or raw\n");
            exit(1);
        }
    }
    if ($a === '--quiet' || $a === '-q') {
        $quiet = true;
    }
}

// ============================================================================
// INPUT READING & VALIDATION
// ============================================================================

// Print welcome for interactive mode
if ($interactive && !$quiet) {
    InputFormatter::printWelcome($interactive);
    InputFormatter::printInstructions();
}

$lines = [];
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line !== '') {
        $lines[] = $line;
    }
}

if (empty($lines)) {
    fwrite(STDERR, "Error: No input provided.\n");
    exit(1);
}

// ============================================================================
// PARSE: BASE COST & PACKAGE COUNT
// ============================================================================

$parts = preg_split('/\s+/', $lines[0]);
if (count($parts) !== 2 || !is_numeric($parts[0]) || !ctype_digit($parts[1])) {
    fwrite(
        STDERR,
        "Error: Invalid first line. Expected: <base_delivery_cost> <no_of_packages>\n"
    );
    exit(1);
}

$baseCost = (float) $parts[0];
$n = (int) $parts[1];

if ($baseCost < 0) {
    fwrite(STDERR, "Error: Base delivery cost must be non-negative.\n");
    exit(1);
}
if ($n < 0) {
    fwrite(STDERR, "Error: Number of packages must be non-negative.\n");
    exit(1);
}

if ($cliVerbose && !$quiet) {
    InputFormatter::printProcessing(
        "Parsed: base cost = {$baseCost}, {$n} package(s)"
    );
}

// ============================================================================
// PARSE: PACKAGES
// ============================================================================

if (count($lines) < ($n + 1)) {
    fwrite(
        STDERR,
        "Error: Expected {$n} package lines but input ended early.\n"
    );
    exit(1);
}

$packages = [];
for ($i = 1; $i <= $n; $i++) {
    $p = preg_split('/\s+/', $lines[$i]);
    if (count($p) !== 4 || !is_numeric($p[1]) || !is_numeric($p[2])) {
        fwrite(
            STDERR,
            "Error: Invalid package line {$i}. "
            . "Expected: <pkg_id> <weight_kg> <distance_km> <offer_code>\n"
        );
        exit(1);
    }

    $id = (string) $p[0];
    $weight = (float) $p[1];
    $dist = (float) $p[2];
    $offer = strtoupper((string) $p[3]);
    if ($offer === 'NA') {
        $offer = null;
    }

    if ($weight < 0 || $dist < 0) {
        fwrite(
            STDERR,
            "Error: Invalid numeric values for package {$id}. "
            . "Weight and distance must be non-negative.\n"
        );
        exit(1);
    }

    // uniqueness check
    foreach ($packages as $existing) {
        if ($existing->id === $id) {
            fwrite(STDERR, "Error: Duplicate package id: {$id}\n");
            exit(1);
        }
    }

    $packages[] = new Package($id, $weight, $dist, $offer);
}

if ($cliVerbose && !$quiet) {
    InputFormatter::printProcessing("Loaded {$n} package(s)");
}

// ============================================================================
// PARSE: VEHICLES (OPTIONAL)
// ============================================================================

$hasScheduling = count($lines) > ($n + 1);
$vehicles = [];

if ($hasScheduling) {
    $schedParts = preg_split('/\s+/', $lines[$n + 1]);
    if (
        count($schedParts) !== 3 || !ctype_digit($schedParts[0])
        || !is_numeric($schedParts[1]) || !is_numeric($schedParts[2])
    ) {
        fwrite(
            STDERR,
            'Error: Invalid vehicle line. '
            . "Expected: <number_of_vehicles> <max_speed_kmph> <max_carriable_weight_kg>\n"
        );
        exit(1);
    }

    $vehicleCount = (int) $schedParts[0];
    $speed = (float) $schedParts[1];
    $capacity = (float) $schedParts[2];

    // Capacity sanity check: no single package should exceed capacity
    foreach ($packages as $pkg) {
        if ($pkg->weightKg > $capacity) {
            fwrite(
                STDERR,
                "Error: Package {$pkg->id} (weight {$pkg->weightKg}) "
                . "exceeds vehicle capacity {$capacity}.\n"
            );
            exit(1);
        }
    }

    for ($i = 1; $i <= $vehicleCount; $i++) {
        $vehicles[] = new Vehicle($i, $speed, $capacity);
    }

    if ($cliVerbose && !$quiet) {
        InputFormatter::printProcessing(
            "Loaded {$vehicleCount} vehicle(s) "
            . "(speed={$speed} kmph, capacity={$capacity} kg)"
        );
    }
}

// ============================================================================
// LOAD OFFERS CONFIGURATION
// ============================================================================

$offers = [new NoOffer()];

try {
    if ($offersConfig !== null) {
        $loadedOffers = ConfigLoader::loadOffers($offersConfig);
        foreach ($loadedOffers as $cfg) {
            $offers[] = new RangeOffer(
                $cfg->code,
                $cfg->discount,
                $cfg->minDistance,
                $cfg->maxDistance,
                $cfg->minWeight,
                $cfg->maxWeight,
                $cfg->distanceMaxType,
            );
        }
        if ($cliVerbose && !$quiet) {
            InputFormatter::printSuccess(
                'Loaded ' . (count($offers) - 1) . " offer(s) from {$offersConfig}"
            );
        }
    } else {
        // Load from default config
        $defaultConfig = __DIR__ . '/config/offers.json';
        if (file_exists($defaultConfig)) {
            $loadedOffers = ConfigLoader::loadOffers($defaultConfig);
            foreach ($loadedOffers as $cfg) {
                $offers[] = new RangeOffer(
                    $cfg->code,
                    $cfg->discount,
                    $cfg->minDistance,
                    $cfg->maxDistance,
                    $cfg->minWeight,
                    $cfg->maxWeight,
                    $cfg->distanceMaxType,
                );
            }
            if ($cliVerbose && !$quiet) {
                InputFormatter::printSuccess(
                    'Loaded ' . (count($offers) - 1) . ' offer(s) from default config'
                );
            }
        }
    }
} catch (\RuntimeException $e) {
    fwrite(STDERR, 'Error loading offers config: ' . $e->getMessage() . "\n");
    exit(1);
}

$registry = new OfferRegistry(...$offers);

// ============================================================================
// CALCULATE COSTS
// ============================================================================

$costCalc = new CostCalculator($baseCost, $registry);
$rows = []; // for printing later
$totalCost = 0.0;
$totalDiscount = 0.0;

foreach ($packages as $p) {
    $c = $costCalc->costFor($p);
    $rows[$p->id] = [
        'id' => $p->id,
        'discount' => $c['discount'],
        'total' => $c['total'],
        'eta' => null,
    ];
    $totalCost += $c['total'];
    $totalDiscount += $c['discount'];
}

if ($cliVerbose && !$quiet) {
    InputFormatter::printSuccess("Calculated costs for {$n} package(s)");
}

// ============================================================================
// ESTIMATE DELIVERY TIMES (IF SCHEDULING INFO PRESENT)
// ============================================================================

if ($hasScheduling) {
    $scheduler = new DeliveryScheduler();
    $etas = $scheduler->estimateTimes($packages, $vehicles);
    foreach ($etas as $pkgId => $eta) {
        $rows[$pkgId]['eta'] = $eta;
    }
    if ($cliVerbose && !$quiet) {
        InputFormatter::printSuccess('Calculated ETAs using greedy scheduler');
    }
}

// ============================================================================
// OUTPUT RESULTS
// ============================================================================

if (!$quiet && !in_array($outputFormat, ['json', 'raw'])) {
    InputFormatter::printSectionHeader('DELIVERY RESULTS');
    OutputFormatter::printTableHeader($hasScheduling);
}

foreach ($packages as $p) {
    $r = $rows[$p->id];

    if ($outputFormat === 'json') {
        $output = ['package_id' => $r['id'], 'discount' => $r['discount'], 'total' => $r['total']];
        if ($hasScheduling) {
            $output['eta_hours'] = $r['eta'];
        }
        fwrite(STDOUT, json_encode($output) . "\n");
    } elseif ($outputFormat === 'raw') {
        if ($hasScheduling) {
            printf(
                "%s %s %s %s\n",
                $r['id'],
                OutputFormatter::formatNumber($r['discount']),
                OutputFormatter::formatNumber($r['total']),
                OutputFormatter::formatNumber($r['eta'] ?? 0.0),
            );
        } else {
            printf(
                "%s %s %s\n",
                $r['id'],
                OutputFormatter::formatNumber($r['discount']),
                OutputFormatter::formatNumber($r['total']),
            );
        }
    } else {
        // human format with table
        OutputFormatter::printTableRow(
            $r['id'],
            $r['discount'],
            $r['total'],
            $r['eta'],
        );
    }
}

if (!$quiet && !in_array($outputFormat, ['json', 'raw'])) {
    OutputFormatter::printTableFooter($hasScheduling);
    OutputFormatter::printSummary(count($packages), $totalCost, $totalDiscount);
}
