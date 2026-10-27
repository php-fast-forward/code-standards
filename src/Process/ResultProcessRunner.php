<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/**
 * Captures separate output channels and status through an optional transport capability.
 *
 * Uppercase requirement terms use BCP 14 (RFC 2119 and RFC 8174).
 * Callers MUST check this capability before using an arbitrary ProcessRunner override.
 */
interface ResultProcessRunner extends ProcessRunner
{
    /**
     * Returns a completed subprocess's native status and separate output bytes.
     *
     * The caller's literal-argument, working-directory and finite-timeout requirements of ProcessRunner apply.
     * Implementations MUST preserve stdout and stderr separately without trimming, decoding or
     * concatenating them, including control bytes and invalid UTF-8. They MUST NOT send captured
     * output to a live output sink. A completed nonzero status MUST remain result data, rather than
     * cause an exception merely because it is nonzero.
     *
     * Failures that prevent completion MUST propagate; implementations MUST NOT fabricate a completed
     * result for them. Callers SHOULD use this optional capability when output and nonzero statuses
     * are both meaningful data. The result does not classify tool output or authorize source writes.
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
