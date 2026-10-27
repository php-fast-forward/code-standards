<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/**
 * Executes bounded argument vectors without shell interpolation.
 *
 * Uppercase requirement terms use BCP 14 (RFC 2119 and RFC 8174).
 * Callers own command authority and possible filesystem or application effects.
 */
interface ProcessRunner
{
    /**
     * Returns the standard-output bytes of a successfully completed subprocess.
     *
     * Callers MUST supply a complete executable/argument vector and a positive finite timeout.
     * Implementations MUST NOT introduce shell interpolation into that vector. The directory
     * identifies the requested child working directory.
     *
     * Failed execution MUST raise an execution failure rather than return output as a successful
     * capture. Callers SHOULD use this operation when they need successful command output as data.
     * Capturing output does not make the selected command read-only.
     *
     * NativeProcessRunner returns unmodified stdout without invoking its live output sink. It
     * rejects invalid timeouts before launch and throws for a completed nonzero exit; its child
     * working directory leaves the caller's directory unchanged. These describe the native
     * implementation, not an additional output-sink or validation algorithm for every override.
     *
     * @param list<string> $arguments Executable followed by literal arguments; callers supply the complete command.
     * @param string $directory Requested working directory in which the command is to run.
     * @param positive-float $timeout Finite running-time limit in seconds; this is not an unlimited mode.
     * @return string Successful stdout; the native transport returns bytes without trimming or stderr concatenation.
     * @throws \InvalidArgumentException In the native transport, when the timeout is nonfinite or not positive.
     * @throws \Symfony\Component\Process\Exception\RuntimeException In the native transport, when execution cannot complete successfully.
     */
    public function capture(array $arguments, string $directory, float $timeout = 120): string;

    /**
     * Runs a subprocess and returns its completed native exit status.
     *
     * The caller's argument, directory and timeout requirements of capture() also apply.
     * Implementations MUST retain the completed native status, including nonzero findings or
     * failures. They MUST NOT report successful completion when no completed status is available.
     *
     * Callers SHOULD select this operation for configured diagnostics and exit-status propagation.
     * The selected command may change files; returning a status is not a read-only guarantee.
     *
     * NativeProcessRunner sends stdout/stderr chunks to its optional sink and stays silent without
     * one. Its chunks retain bytes, but it does not establish total channel ordering or bounded
     * memory. Native startup/timeout failures and exceptions from its sink can propagate instead
     * of returning a status. Other overrides document their own output-delivery and exception details.
     *
     * @param list<string> $arguments Executable followed by literal arguments; no shell command string is accepted.
     * @param string $directory Requested child working directory; the native transport leaves the caller's unchanged.
     * @param positive-float $timeout Finite running-time limit in seconds; zero never selects an unlimited mode.
     * @return int Completed native exit status, including nonzero tool findings or failures.
     * @throws \InvalidArgumentException In the native transport, when the timeout is nonfinite or not positive.
     * @throws \Symfony\Component\Process\Exception\RuntimeException In the native transport, when execution cannot complete.
     * @throws \Throwable In the native transport, when a configured sink fails while handling a chunk.
     */
    public function run(array $arguments, string $directory, float $timeout = 300): int;
}
