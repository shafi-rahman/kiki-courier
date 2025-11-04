<?php

declare(strict_types=1);

namespace App\UI;

/**
 * Provides user-friendly input prompts and formatting for interactive mode.
 */
final class InputFormatter
{
    private const WELCOME_MESSAGE = <<<'EOT'
╔════════════════════════════════════════════════════════════════╗
║                    KIKI COURIER SERVICE                        ║
║          Delivery Cost Calculator & Scheduler CLI               ║
╚════════════════════════════════════════════════════════════════╝

EOT;

    /**
     * Print welcome message.
     */
    public static function printWelcome(bool $isInteractive): void
    {
        if ($isInteractive) {
            fwrite(STDERR, self::WELCOME_MESSAGE);
        }
    }

    /**
     * Print input instructions.
     */
    public static function printInstructions(): void
    {
        $msg = <<<'EOT'
INSTRUCTIONS:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 1: Enter base delivery cost and number of packages
Format:  <base_cost> <count>
Example: 100 5

Step 2: Enter package details (one per line)
Format:  <id> <weight_kg> <distance_km> <offer_code>
Example: PKG1 50 30 OFR001
         PKG2 75 125 OFFR0008
         PKG3 175 100 OFR003

Step 3: (Optional) Enter vehicle information for scheduling
Format:  <vehicle_count> <speed_kmph> <capacity_kg>
Example: 2 70 200

Leave empty and press Ctrl+D (Mac/Linux) or Ctrl+Z then Enter (Windows) to finish.

Available Offer Codes:
  • OFR001: 10% off (distance < 200, weight 70-200)
  • OFR002:  7% off (distance 50-150, weight 100-250)
  • OFR003:  5% off (distance 50-250, weight 10-150)
  • NA:      No offer

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

EOT;
        fwrite(STDERR, $msg);
    }

    /**
     * Print section header.
     *
     * @param string $title Section title
     */
    public static function printSectionHeader(string $title): void
    {
        fwrite(STDERR, "\n► {$title}\n");
    }

    /**
     * Print processing message.
     *
     * @param string $message Message to print
     */
    public static function printProcessing(string $message): void
    {
        fwrite(STDERR, "  ℹ {$message}\n");
    }

    /**
     * Print success message.
     *
     * @param string $message Message to print
     */
    public static function printSuccess(string $message): void
    {
        fwrite(STDERR, "  ✓ {$message}\n");
    }

    /**
     * Print warning message.
     *
     * @param string $message Message to print
     */
    public static function printWarning(string $message): void
    {
        fwrite(STDERR, "  ⚠ {$message}\n");
    }

    /**
     * Print error message.
     *
     * @param string $message Message to print
     */
    public static function printError(string $message): void
    {
        fwrite(STDERR, "  ✗ {$message}\n");
    }
}
