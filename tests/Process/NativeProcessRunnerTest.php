<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Process;

use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ResultProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/** Exercises finite real subprocess bounds without waiting for production-length deadlines. */
final class NativeProcessRunnerTest extends TestCase
{
    /** Both channels enforce short limits and accept a larger positive override. */
    public function testFiniteTimeoutsApplyToCapturedAndStreamedProcesses(): void
    {
        $process = new NativeProcessRunner();
        foreach (['capture', 'run', 'captureResult'] as $method) {
            try {
                $process->{$method}([PHP_BINARY, '-r', 'usleep(250000);'], sys_get_temp_dir(), 0.01);
                self::fail('A controlled long subprocess must time out.');
            } catch (ProcessTimedOutException $exception) {
                self::assertSame(0.01, $exception->getExceededTimeout());
            }
        }
        $arguments = [PHP_BINARY, '-r', 'usleep(50000); echo "completed";'];
        self::assertSame('completed', $process->capture($arguments, sys_get_temp_dir(), 2));
        self::assertSame('completed', $process->captureResult($arguments, sys_get_temp_dir(), 2)->stdout);
        $output = '';
        $streamed = new NativeProcessRunner(static function (string $type, string $bytes) use (&$output): void {
            $output .= $bytes;
        });
        self::assertSame(0, $streamed->run($arguments, sys_get_temp_dir(), 2));
        self::assertSame('completed', $output);
    }

    /** Zero, negative and nonfinite values cannot silently become Symfony's unlimited mode. */
    public function testInvalidBoundsFailBeforeLaunchingEitherChannel(): void
    {
        foreach (['capture', 'run', 'captureResult'] as $method) {
            foreach ([0.0, -0.1, INF, -INF, NAN] as $timeout) {
                try {
                    (new NativeProcessRunner())->{$method}([PHP_BINARY, '-r', 'echo "unexpected";'], sys_get_temp_dir(), $timeout);
                    self::fail('Invalid subprocess limits must be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString('positive finite', $exception->getMessage());
                }
            }
        }
    }

    /** Binary bytes and nonzero statuses remain separate data and never reach the streaming output sink. */
    public function testResultCaptureRetainsExactBytesAndNativeStatuses(): void
    {
        $writes = [];
        $runner = new NativeProcessRunner(static function (string $type, string $bytes) use (&$writes): void {
            $writes[] = [$type, $bytes];
        });
        self::assertInstanceOf(ResultProcessRunner::class, $runner);
        $source = <<<'PHP'
            fwrite(STDOUT, "out\0\xFF\033[31m\n");
            fwrite(STDERR, "err\0\xFE\033[0m\n");
            exit((int) $argv[1]);
            PHP;

        foreach ([0, 2, 255] as $status) {
            $result = $runner->captureResult([PHP_BINARY, '-r', $source, (string) $status], sys_get_temp_dir());
            self::assertSame($status, $result->exitCode);
            self::assertSame("out\0\xFF\033[31m\n", $result->stdout);
            self::assertSame("err\0\xFE\033[0m\n", $result->stderr);
        }

        self::assertSame([], $writes);
    }

    /** Captured execution preserves the selected cwd/argv and the existing credential-removal policy. */
    public function testResultCaptureUsesTheExistingProcessBoundary(): void
    {
        $before = getcwd();
        $directory = realpath(sys_get_temp_dir());
        self::assertNotFalse($directory);
        $previous = [];
        foreach (['GH_TOKEN', 'GITHUB_TOKEN', 'COMPOSER_AUTH'] as $name) {
            $previous[$name] = getenv($name);
            putenv($name . '=synthetic-test-value');
        }

        try {
            $result = (new NativeProcessRunner())->captureResult([
                PHP_BINARY, '-r',
                'echo getcwd(), "\n", $argv[1]; foreach (["GH_TOKEN", "GITHUB_TOKEN", "COMPOSER_AUTH"] as $name) { if (false !== getenv($name)) { exit(19); } }',
                'literal argument with spaces',
            ], $directory);
        } finally {
            foreach ($previous as $name => $value) {
                putenv(false === $value ? $name : $name . '=' . $value);
            }
        }

        self::assertSame(0, $result->exitCode);
        self::assertSame($directory . "\nliteral argument with spaces", $result->stdout);
        self::assertSame('', $result->stderr);
        self::assertSame($before, getcwd());
    }

    /** Native startup errors propagate instead of becoming an invented successful process result. */
    public function testResultCaptureDoesNotInventAResultForStartupFailure(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\RuntimeException::class);
        (new NativeProcessRunner())->captureResult(
            [PHP_BINARY, '-r', 'echo "unexpected";'],
            sys_get_temp_dir() . '/code-standards-absent-' . bin2hex(random_bytes(16)),
        );
    }

    /** Existing captured execution still throws on a completed nonzero process instead of changing its API. */
    public function testLegacyCaptureRetainsItsFailureContract(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessFailedException::class);
        (new NativeProcessRunner())->capture([PHP_BINARY, '-r', 'exit(2);'], sys_get_temp_dir());
    }
}
