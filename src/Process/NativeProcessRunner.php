<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

use InvalidArgumentException;
use OverflowException;
use Symfony\Component\Process\Process;

/** Native transport; callers own selection and operation policy. */
final class NativeProcessRunner implements LimitedResultProcessRunner
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

    /** Retains bounded channel bytes through Symfony's callback without its internal output buffers. */
    public function captureLimitedResult(array $arguments, string $directory, int $stdoutLimit, int $stderrLimit, float $timeout = 300): ProcessResult
    {
        if ($stdoutLimit < 1 || $stderrLimit < 1) {
            throw new InvalidArgumentException('Captured output limits must be positive byte counts.');
        }

        $process = $this->createProcess($arguments, $directory, $timeout);
        $process->disableOutput();
        $channels = [Process::OUT => '', Process::ERR => ''];
        $limits = [Process::OUT => $stdoutLimit, Process::ERR => $stderrLimit];
        $collect = true;

        try {
            $status = $process->run(static function (string $type, string $bytes) use (&$channels, $limits, &$collect): void {
                if (!$collect) {
                    return;
                }
                if (strlen($bytes) > $limits[$type] - strlen($channels[$type])) {
                    $collect = false;
                    throw new OverflowException('Captured ' . $type . ' exceeded its byte limit.');
                }

                $channels[$type] .= $bytes;
            });
        } finally {
            $collect = false;
            $process->stop(0);
        }

        return new ProcessResult($status, $channels[Process::OUT], $channels[Process::ERR]);
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
