<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Executes bounded argument arrays without shell interpolation. */
interface ProcessRunner
{
    /** @param list<string> $arguments Returns stdout or throws on failure. */
    public function capture(array $arguments, string $directory): string;

    /** @param list<string> $arguments Streams diagnostics and returns the actual exit code. */
    public function run(array $arguments, string $directory): int;
}
