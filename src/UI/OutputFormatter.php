<?php

declare(strict_types=1);

namespace App\UI;

/**
 * Handles output formatting in various styles (human-readable, JSON, etc.).
 */
final class OutputFormatter
{
    /**
     * Format output row for human-readable display.
     *
     * @param string $pkgId Package ID
     * @param float $discount Discount amount
     * @param float $total Total cost after discount
     * @param float|null $eta Estimated delivery time (optional)
     * @return string Formatted output row
     */
    public static function formatRowHuman(
        string $pkgId,
        float $discount,
        float $total,
        ?float $eta = null
    ): string {
        $discountStr = self::formatNumber($discount);
        $totalStr = self::formatNumber($total);

        if ($eta === null) {
            return sprintf('%s %s %s', $pkgId, $discountStr, $totalStr);
        }

        $etaStr = self::formatNumber($eta);
        return sprintf('%s %s %s %s', $pkgId, $discountStr, $totalStr, $etaStr);
    }

    /**
     * Format number for output (2 decimals, stripped of trailing zeros).
     *
     * @param float $value Value to format
     * @return string Formatted number
     */
    public static function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Format as JSON.
     *
     * @param array<string,mixed> $data Data to format
     * @return string JSON string
     */
    public static function formatJson(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json === false ? '{}' : $json;
    }

    /**
     * Print a table header for delivery results.
     *
     * @param bool $hasEta Whether ETA column is present
     */
    public static function printTableHeader(bool $hasEta = false): void
    {
        if ($hasEta) {
            fwrite(STDERR, "\n┌──────────┬──────────┬──────────┬──────────────┐\n");
            fwrite(STDERR, "│ Package  │ Discount │  Total   │    ETA (h)   │\n");
            fwrite(STDERR, "├──────────┼──────────┼──────────┼──────────────┤\n");
        } else {
            fwrite(STDERR, "\n┌──────────┬──────────┬──────────┐\n");
            fwrite(STDERR, "│ Package  │ Discount │  Total   │\n");
            fwrite(STDERR, "├──────────┼──────────┼──────────┤\n");
        }
    }

    /**
     * Print a table row for delivery results.
     *
     * @param string $pkgId Package ID
     * @param float $discount Discount amount
     * @param float $total Total cost
     * @param float|null $eta Estimated delivery time (optional)
     */
    public static function printTableRow(
        string $pkgId,
        float $discount,
        float $total,
        ?float $eta = null
    ): void {
        $discountStr = str_pad(self::formatNumber($discount), 8, ' ', STR_PAD_LEFT);
        $totalStr = str_pad(self::formatNumber($total), 8, ' ', STR_PAD_LEFT);
        $pkgStr = str_pad($pkgId, 8, ' ', STR_PAD_RIGHT);

        if ($eta === null) {
            $row = sprintf("│ %s │%s │%s │\n", $pkgStr, $discountStr, $totalStr);
        } else {
            $etaStr = str_pad(self::formatNumber($eta), 12, ' ', STR_PAD_LEFT);
            $row = sprintf("│ %s │%s │%s │%s │\n", $pkgStr, $discountStr, $totalStr, $etaStr);
        }
        fwrite(STDOUT, $row);
    }

    /**
     * Print table footer.
     *
     * @param bool $hasEta Whether ETA column is present
     */
    public static function printTableFooter(bool $hasEta = false): void
    {
        if ($hasEta) {
            fwrite(STDERR, "└──────────┴──────────┴──────────┴──────────────┘\n");
        } else {
            fwrite(STDERR, "└──────────┴──────────┴──────────┘\n");
        }
    }

    /**
     * Print summary statistics.
     *
     * @param int $packageCount Total packages processed
     * @param float $totalCost Total cost for all packages
     * @param float $totalDiscount Total discount given
     */
    public static function printSummary(
        int $packageCount,
        float $totalCost,
        float $totalDiscount
    ): void {
        fwrite(
            STDERR,
            "\nSummary:\n"
            . "  Packages: {$packageCount}\n"
            . '  Total Cost: ' . self::formatNumber($totalCost) . "\n"
            . '  Total Discount: ' . self::formatNumber($totalDiscount) . "\n\n"
        );
    }
}
