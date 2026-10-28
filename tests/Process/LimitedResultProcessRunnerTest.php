<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Process;

use FastForward\CodeStandards\Process\NativeProcessRunner;
use OverflowException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/** Exercises actual callback capture, overflow cleanup and unchanged transport policies. */
final class LimitedResultProcessRunnerTest extends TestCase
{
    /** Completed binary channels and native nonzero statuses remain data, without streaming. */
    public function testExactChannelsAtTheirLimitsRemainUnmodified(): void
    {
        $runner = new NativeProcessRunner(static function (): void {
            self::fail('Capture must not stream.');
        });
        $result = $runner->captureLimitedResult([
            PHP_BINARY, '-r', 'fwrite(STDOUT, "a\0\xff"); fwrite(STDERR, "b\0\xfe"); exit(23);',
        ], sys_get_temp_dir(), 3, 3);

        self::assertSame(23, $result->exitCode);
        self::assertSame("a\0\xff", $result->stdout);
        self::assertSame("b\0\xfe", $result->stderr);
    }

    /** Both overflow channels stop the known direct child; cleanup does not rethrow from its callback. */
    public function testOverflowTerminatesTheOwnedChild(): void
    {
        foreach (['STDOUT', 'STDERR'] as $channel) {
            $marker = tempnam(sys_get_temp_dir(), 'limited-child-');
            try {
                (new NativeProcessRunner())->captureLimitedResult([
                    PHP_BINARY, '-r',
                    'file_put_contents($argv[1], (string) getmypid()); fwrite(' . $channel . ', str_repeat("x", 65536)); usleep(2000000); file_put_contents($argv[1], "survived");',
                    $marker,
                ], sys_get_temp_dir(), 64, 64, 3);
                self::fail('Oversized output must raise an exception.');
            } catch (OverflowException $exception) {
                self::assertStringContainsString('byte limit', $exception->getMessage());
                $pid = file_get_contents($marker);
                self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $pid);
                if (function_exists('posix_kill')) {
                    self::assertFalse(posix_kill((int) $pid, 0), 'The exact fixture child must have exited.');
                }
            } finally {
                unlink($marker);
            }
        }
    }

    /** Native timeout and credential removal remain effective for limited capture. */
    public function testTimeoutAndEnvironmentUseTheExistingBoundary(): void
    {
        $runner = new NativeProcessRunner();
        $result = $runner->captureLimitedResult([
            PHP_BINARY, '-r', 'foreach (["GH_TOKEN", "GITHUB_TOKEN", "COMPOSER_AUTH"] as $key) { if (false !== getenv($key)) { exit(19); } } echo "ok";',
        ], sys_get_temp_dir(), 2, 2);
        self::assertSame(0, $result->exitCode);
        self::assertSame('ok', $result->stdout);
        $this->expectException(ProcessTimedOutException::class);
        $runner->captureLimitedResult([PHP_BINARY, '-r', 'usleep(200000);'], sys_get_temp_dir(), 10, 10, 0.01);
    }

    /** Invalid byte limits fail without starting the selected executable. */
    public function testInvalidOutputLimitsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NativeProcessRunner())->captureLimitedResult([PHP_BINARY, '-r', 'exit(99);'], sys_get_temp_dir(), 0, 1);
    }
}
