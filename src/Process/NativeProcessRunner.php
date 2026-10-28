<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

use InvalidArgumentException;
use Symfony\Component\Process\Process;

/** Native transport; callers own selection and operation policy. */
final class NativeProcessRunner implements ResultProcessRunner
{
    /** Both captured and streamed configuration subprocesses receive the same credential policy. */
    private const array ENVIRONMENT = ['GH_TOKEN' => false, 'GITHUB_TOKEN' => false, 'COMPOSER_AUTH' => false];

    /** @param \Closure(string,string):void|null $write Explicit output sink; null keeps this library transport silent. */
    public function __construct(private readonly ?\Closure $write = null) {}

    /** Captures native output as bytes, including Git paths and Rector's JSON inventory. */
    public function capture(array $arguments, string $directory, float $timeout = 120): string
    {
        $process = $this->createProcess($arguments, $directory, $timeout);
        $process->mustRun();

        return $process->getOutput();
    }

    /** Runs one tool, streaming its output without changing its failure status. */
    public function run(array $arguments, string $directory, float $timeout = 300): int
    {
        $process = $this->createProcess($arguments, $directory, $timeout);

        return $process->run($this->write);
    }

    /** Captures both channels without invoking the streaming sink or interpreting native failure statuses. */
    public function captureResult(array $arguments, string $directory, float $timeout = 300): ProcessResult
    {
        $process = $this->createProcess($arguments, $directory, $timeout);
        $status = $process->run();

        return new ProcessResult($status, $process->getOutput(), $process->getErrorOutput());
    }

    /** @param list<string> $arguments Positive finite bounds never become Symfony's zero/null unlimited mode. */
    private function createProcess(array $arguments, string $directory, float $timeout): Process
    {
        if (
            !is_finite($timeout)
            || $timeout <= 0
        ) {
            throw new InvalidArgumentException('Subprocess timeout must be a positive finite number of seconds.');
        }

        return new Process($arguments, $directory, self::ENVIRONMENT, timeout: $timeout);
    }
}
