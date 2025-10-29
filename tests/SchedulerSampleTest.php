<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchedulerSampleTest extends TestCase
{
    private function runProgram(string $fixture): string
    {
        $cmd = sprintf(
            'php %s < %s',
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

    public function test_official_sample_matches_expected(): void
    {
        $actual   = $this->norm($this->runProgram('sample1.txt'));
        $expected = $this->norm(file_get_contents(__DIR__ . '/fixtures/expected1.txt'));
        $this->assertSame($expected, $actual);
        $this->assertStringContainsString('4.19', $actual); // sanity check
    }

    public function test_custom_sample_matches_expected(): void
    {
        $actual   = $this->norm($this->runProgram('sample2.txt'));
        $expected = $this->norm(file_get_contents(__DIR__ . '/fixtures/expected2.txt'));
        $this->assertSame($expected, $actual);
    }
}
