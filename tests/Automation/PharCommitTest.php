<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Exercises the data-only writer with local API fixtures, without creating GitHub commits. */
final class PharCommitTest extends TestCase
{
    public function testReceiptBindsRealBytesToTheRunAndAttempt(): void
    {
        $directory = sys_get_temp_dir() . '/standards-receipt-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            file_put_contents($directory . '/binary', 'fixture executable bytes');
            $process = new Process([
                PHP_BINARY, dirname(__DIR__, 2) . '/.github/PharCommit/receipt.php',
                $directory . '/binary', str_repeat('a', 40), '123', '2', $directory . '/receipt.json',
            ], timeout: 10);
            $process->mustRun();
            self::assertSame([
                'version' => 1,
                'source_sha' => str_repeat('a', 40),
                'run_id' => 123,
                'run_attempt' => 2,
                'sha256' => hash_file('sha256', $directory . '/binary'),
            ], json_decode(file_get_contents($directory . '/receipt.json'), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($directory);
        }
    }

    public function testArtifactProvenanceAndAtomicCommitGuards(): void
    {
        $process = new Process(['node', '--test', __DIR__ . '/PharCommit.test.cjs'], timeout: 30);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
    }
}
