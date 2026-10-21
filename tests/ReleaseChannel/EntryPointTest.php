<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\ReleaseChannel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Verifies invocation failures of the real entrypoint without allowing network requests. */
final class EntryPointTest extends TestCase
{
    /** Wrong targets and missing credentials terminate before constructing authenticated operations. */
    public function testInvalidInvocationAndMissingTokenFailWithoutPrintingSecrets(): void
    {
        $script = dirname(__DIR__, 2) . '/.github/update-release-channels.php';
        foreach ([[], ['foreign/library', 'v0.1.0', str_repeat('1', 40)],
            ['php-fast-forward/code-standards', 'v0.1.0', str_repeat('1', 40)]] as $arguments) {
            $process = new Process([PHP_BINARY, $script, ...$arguments], env: ['GH_TOKEN' => false], timeout: 10);
            self::assertSame(2, $process->run());
            self::assertSame('', $process->getOutput());
            self::assertStringNotContainsString('Authorization', $process->getErrorOutput());
        }
    }
}
