<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OffersAndPricingTest extends TestCase
{
    private function runProgram(string $fixture): string
    {
        $cmd = sprintf(
            'php %s --format=raw --quiet < %s',
            escapeshellarg(__DIR__ . '/../main.php'),
            escapeshellarg(__DIR__ . "/fixtures/{$fixture}")
        );
        $out = shell_exec($cmd);
        $this->assertIsString($out, 'Program did not run or produced no output.');
        return $out;
    }

    private function norm(string $s): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $s));
    }

    public function test_offers_and_invalid_code_in_problem1_mode(): void
    {
        $actual   = $this->norm($this->runProgram('offers_only.txt'));
        $expected = $this->norm(file_get_contents(__DIR__ . '/fixtures/expected_offers.txt'));

        $this->assertSame($expected, $actual);

        $lines = explode("\n", $actual);
        $this->assertCount(4, $lines, 'Should print exactly one line per package.');
        // Invalid code OFFR002 must yield zero discount
        $this->assertStringContainsString('PKG4 0', $lines[3]);
    }
}
