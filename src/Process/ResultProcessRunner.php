<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Adds optional capture of output and status as data. */
interface ResultProcessRunner extends ProcessRunner
{
    /**
     * Captures output and status as data.
     *
     * Stdout and stderr MUST remain separate, unmodified byte strings, without streaming.
     * A completed nonzero status MUST remain data; failure before completion MUST raise an exception.
     *
     * @param list<string> $arguments Executable followed by literal arguments, without shell interpolation.
     * @param string $directory Child working directory, leaving the caller's unchanged.
     * @param float $timeout Positive, finite execution limit in seconds.
     * @return ProcessResult Completed result, without interpreting diagnostics or classifying success.
     * @throws \Throwable When execution cannot complete or the timeout is invalid.
     */
    public function captureResult(array $arguments, string $directory, float $timeout = 300): ProcessResult;
}
