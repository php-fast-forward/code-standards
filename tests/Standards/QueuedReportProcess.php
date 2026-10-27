<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Standards;

use FastForward\CodeStandards\Process\LimitedResultProcessRunner;
use FastForward\CodeStandards\Process\ProcessResult;

/** Supplies focused process outcomes without executing a checker or streaming human output. */
final class QueuedReportProcess implements LimitedResultProcessRunner
{
    public array $commands = [];

    /** @param list<ProcessResult|\Throwable> $styleResults Completed ECS outcomes or transport failures. */
    public function __construct(private array $styleResults = [], private ?ProcessResult $syntaxResult = null) {}

    public function capture(array $arguments, string $directory, float $timeout = 120): string
    {
        throw new \LogicException('Legacy capture is not JSON transport.');
    }

    public function run(array $arguments, string $directory, float $timeout = 300): int
    {
        throw new \LogicException('JSON transport must not stream.');
    }

    public function captureResult(array $arguments, string $directory, float $timeout = 300): ProcessResult
    {
        throw new \LogicException('Unlimited result capture is not JSON transport.');
    }

    public function captureLimitedResult(array $arguments, string $directory, int $stdoutLimit, int $stderrLimit, float $timeout = 300): ProcessResult
    {
        $this->commands[] = $arguments;
        $result = in_array('-l', $arguments, true)
            ? ($this->syntaxResult ?? new ProcessResult(0, 'native lint success', ''))
            : (array_shift($this->styleResults) ?? new ProcessResult(0, '{"totals":{"errors":0,"diffs":0},"files":[]}', ''));
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
