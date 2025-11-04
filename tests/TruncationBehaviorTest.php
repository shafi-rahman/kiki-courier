<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TruncationBehaviorTest extends TestCase
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

    public function test_two_decimal_truncation_is_applied(): void
    {
        $actual = $this->runProgram('sample1.txt');

        // key ETAs that only appear when truncation is correct
        $this->assertStringContainsString('3.98', $actual);
        $this->assertStringContainsString('1.78', $actual);
        $this->assertStringContainsString('1.42', $actual);
        $this->assertStringContainsString('0.85', $actual);
        $this->assertStringContainsString('4.19', $actual);
    }
}
