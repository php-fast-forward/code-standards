<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Optional lossless capture capability; existing ProcessRunner implementations remain valid. */
interface ResultProcessRunner extends ProcessRunner
{
    /**
     * Captures a completed process without streaming or throwing merely for a nonzero exit.
     *
     * @param list<string> $arguments Literal executable and argument vector, without shell interpolation.
     * @param string $directory Explicit child working directory; the caller's directory remains unchanged.
     * @param positive-float $timeout Finite maximum seconds using the same transport bounds as run().
     * @return ProcessResult Exact native status and separate output bytes, without a clean/failure interpretation.
     * @throws \InvalidArgumentException When the timeout is nonfinite or not positive.
     * @throws \Symfony\Component\Process\Exception\RuntimeException When execution cannot complete, including startup or timeout failure.
     */
    public function captureResult(array $arguments, string $directory, float $timeout = 300): ProcessResult;
}
