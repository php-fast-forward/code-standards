<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Process;

use FastForward\CodeStandards\Process\NativeProcessRunner;
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
        foreach (['capture', 'run'] as $method) {
            try {
                $process->{$method}([PHP_BINARY, '-r', 'usleep(250000);'], sys_get_temp_dir(), 0.01);
                self::fail('A controlled long subprocess must time out.');
            } catch (ProcessTimedOutException $exception) {
                self::assertSame(0.01, $exception->getExceededTimeout());
            }
        }
        $arguments = [PHP_BINARY, '-r', 'usleep(50000); echo "completed";'];
        self::assertSame('completed', $process->capture($arguments, sys_get_temp_dir(), 2));
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
        foreach (['capture', 'run'] as $method) {
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
}
