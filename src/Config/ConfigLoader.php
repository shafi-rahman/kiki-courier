<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Loads and validates offer configuration from JSON files.
 */
final class ConfigLoader
{
    /**
     * Load offers from a JSON configuration file.
     *
     * @param string $filePath Path to the JSON config file
     * @return OfferConfig[] Array of validated offer configurations
     * @throws RuntimeException if file not found or invalid
     */
    public static function loadOffers(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("Config file not found or unreadable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file: {$filePath}");
        }

        $data = json_decode($content, associative: true);
        if (!is_array($data)) {
            throw new RuntimeException('Config file must be valid JSON');
        }

        if (!isset($data['offers']) || !is_array($data['offers'])) {
            throw new RuntimeException('Config must contain "offers" array');
        }

        $offers = [];
        foreach ($data['offers'] as $idx => $offer) {
            try {
                $offers[] = self::parseOfferEntry($offer);
            } catch (\InvalidArgumentException $e) {
                throw new RuntimeException(
                    "Invalid offer at index {$idx}: " . $e->getMessage()
                );
            }
        }

        return $offers;
    }

    /**
     * Parse a single offer entry from the config.
     *
     * @param mixed $entry The offer entry to parse
     * @return OfferConfig Parsed configuration
     * @throws \InvalidArgumentException if entry is invalid
     */
    private static function parseOfferEntry(mixed $entry): OfferConfig
    {
        if (!is_array($entry)) {
            throw new \InvalidArgumentException('Offer entry must be an object');
        }

        $code = $entry['code'] ?? null;
        if (!is_string($code) || empty($code)) {
            throw new \InvalidArgumentException('Offer must have a non-empty "code" field');
        }

        $discount = $entry['discount'] ?? null;
        if (!is_numeric($discount)) {
            throw new \InvalidArgumentException("Offer {$code}: discount must be numeric");
        }

        $distance = $entry['distance'] ?? [];
        $weight = $entry['weight'] ?? [];

        return new OfferConfig(
            code: $code,
            discount: (float) $discount,
            minDistance: self::extractNumericOrNull($distance, 'min'),
            maxDistance: self::extractNumericOrNull($distance, 'max'),
            distanceMaxType: self::extractBoundaryType($distance, 'maxType', 'inclusive_max'),
            minWeight: self::extractNumericOrNull($weight, 'min'),
            maxWeight: self::extractNumericOrNull($weight, 'max'),
            weightMinType: self::extractBoundaryType($weight, 'minType', 'inclusive_min'),
            weightMaxType: self::extractBoundaryType($weight, 'maxType', 'inclusive_max'),
        );
    }

    /**
     * Extract a numeric value or return null.
     *
     * @param array<string,mixed> $data The data array
     * @param string $key The key to extract
     * @return float|null The numeric value or null
     */
    private static function extractNumericOrNull(array $data, string $key): ?float
    {
        if (!isset($data[$key])) {
            return null;
        }
        $value = $data[$key];
        if ($value === null) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Value for '{$key}' must be numeric or null");
        }
        return (float) $value;
    }

    /**
     * Extract boundary type or use default.
     *
     * @param array<string,mixed> $data The data array
     * @param string $key The key to extract
     * @param string $default Default value
     * @return string The boundary type
     */
    private static function extractBoundaryType(
        array $data,
        string $key,
        string $default
    ): string {
        if (!isset($data[$key])) {
            return $default;
        }
        $value = $data[$key];
        if (!in_array($value, ['inclusive_min', 'inclusive_max', 'exclusive_min', 'exclusive_max'])) {
            throw new \InvalidArgumentException(
                "Invalid boundary type for '{$key}': {$value}"
            );
        }
        return $value;
    }
}
