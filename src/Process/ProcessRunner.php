<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Executes bounded commands with literal arguments. */
interface ProcessRunner
{
    /**
     * Captures successful standard output.
     *
     * A nonzero exit MUST raise an exception rather than return successful output.
     *
     * @param list<string> $arguments Executable followed by literal arguments, without shell interpolation.
     * @param string $directory Child working directory.
     * @param positive-float $timeout Finite execution limit in seconds.
     * @return string Successful stdout, excluding stderr diagnostics.
     * @throws \Throwable When execution cannot complete successfully.
     */
    public function capture(array $arguments, string $directory, float $timeout = 120): string;

    /**
     * Runs a command and returns its exit status.
     *
     * Completed statuses, including nonzero ones, MUST be returned unchanged.
     * Diagnostics MAY be emitted; failure before completion MUST raise an exception.
     *
     * @param list<string> $arguments Executable followed by literal arguments, without shell interpolation.
     * @param string $directory Child working directory.
     * @param positive-float $timeout Finite execution limit in seconds.
     * @return int Completed command status; interpreting tool-specific outcomes belongs to the caller.
     * @throws \Throwable When execution cannot complete.
     */
    public function run(array $arguments, string $directory, float $timeout = 300): int;
}
