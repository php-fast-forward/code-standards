<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Executes bounded argument arrays without shell interpolation. */
interface ProcessRunner
{
    /**
     * @param list<string> $arguments Returns stdout or throws on failure.
     * @param positive-float $timeout Finite maximum seconds for this subprocess; defaults retain Git's existing limit.
     */
    public function capture(array $arguments, string $directory, float $timeout = 120): string;

    /**
     * @param list<string> $arguments Streams diagnostics and returns the actual exit code.
     * @param positive-float $timeout Finite maximum seconds for this subprocess; default formatting limit is unchanged.
     */
    public function run(array $arguments, string $directory, float $timeout = 300): int;
}
