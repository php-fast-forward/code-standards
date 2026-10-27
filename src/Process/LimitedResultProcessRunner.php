<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Adds capture with explicit retained-output limits. */
interface LimitedResultProcessRunner extends ResultProcessRunner
{
    /**
     * Captures a completed result within caller-selected byte limits.
     *
     * Channels MUST remain separate and unmodified without streaming. Exceeding either
     * positive limit MUST raise an exception rather than return truncated output.
     *
     * @param list<string> $arguments Executable followed by literal arguments.
     * @param string $directory Child working directory.
     * @param positive-int $stdoutLimit Maximum retained stdout bytes.
     * @param positive-int $stderrLimit Maximum retained stderr bytes.
     * @param float $timeout Positive, finite execution limit in seconds.
     * @return ProcessResult Completed status and exact output within the limits.
     * @throws \Throwable When execution cannot complete within the supplied bounds.
     */
    public function captureLimitedResult(array $arguments, string $directory, int $stdoutLimit, int $stderrLimit, float $timeout = 300): ProcessResult;
}
