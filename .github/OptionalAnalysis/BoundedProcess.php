<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use FastForward\CodeStandards\Process\ProcessResult;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/** Captures one owned analyzer invocation with finite lifetime and bounded native report/diagnostic channels. */
final class BoundedProcess
{
    public const REPORT_BYTES = 2097152;
    private const STDERR_BYTES = 65536;
    private const TIMEOUT_SECONDS = 300;
    private const CHUNK_BYTES = 8192;
    private const PRIVATE_ENVIRONMENT_FIELDS = [
        'GH_TOKEN', 'GITHUB_TOKEN', 'COMPOSER_AUTH',
        'GITHUB_OUTPUT', 'GITHUB_STEP_SUMMARY', 'GITHUB_ENV', 'GITHUB_PATH', 'GITHUB_STATE',
        'ACTIONS_RUNTIME_TOKEN', 'ACTIONS_ID_TOKEN_REQUEST_TOKEN', 'ACTIONS_ID_TOKEN_REQUEST_URL',
    ];

    /**
     * Starts a literal argv subprocess, never a shell or a consumer autoload in this parent.
     *
     * The timeout covers the entire invocation, including Rector's native preflight and preview.
     * Only the subprocess created here may be stopped on failure; output overflow is operational error.
     *
     * @param list<string> $arguments Installed Code Standards entrypoint and explicitly validated options.
     */
    public function capture(array $arguments, string $directory, float $timeout = self::TIMEOUT_SECONDS): ProcessResult
    {
        $this->assertPrerequisites($timeout);
        $deadline = hrtime(true) + (int) ($timeout * 1_000_000_000);
        $environment = getenv();
        foreach (self::PRIVATE_ENVIRONMENT_FIELDS as $name) {
            unset($environment[$name]);
        }
        $temporary = $this->temporary();
        $environment['TMPDIR'] = $temporary;
        try {
            $process = @proc_open([
                '/usr/bin/setsid', '--wait', PHP_BINARY, __DIR__ . '/launch.php', ...$arguments,
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => ['pipe', 'w']], $pipes, $directory, $environment);
        } catch (Throwable $exception) {
            $this->removeTemporary($temporary);

            throw $exception;
        }
        if (!is_resource($process)) {
            $this->removeTemporary($temporary);
            throw new RuntimeException('Cannot start the owned optional-analyzer process.');
        }
        $pid = proc_get_status($process)['pid'];
        $confirmed = false;
        try {
            $this->confirmGroup($pipes[3], $pid, min($deadline, hrtime(true) + 2_000_000_000));
            $confirmed = true;
            if (1 !== fwrite($pipes[0], 'S')) {
                throw new RuntimeException('Cannot authorize the owned analyzer to start.');
            }
            fclose($pipes[0]);
            fclose($pipes[3]);
            unset($pipes[0], $pipes[3]);
            [$stdout, $stderr, $status] = $this->read($process, $pipes, $deadline);
        } finally {
            if ($confirmed) {
                // This group was created and verified before any consumer configuration could execute.
                @posix_kill(-$pid, 15);
                @posix_kill(-$pid, 9);
            } else {
                // Before the start handshake, only the trusted launcher can exist.
                @proc_terminate($process, 9);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $closed = proc_close($process);
            $this->removeTemporary($temporary);
        }
        $status ??= $closed;
        if ($status < 0) {
            throw new RuntimeException('Optional analyzer completion status is unavailable.');
        }

        return new ProcessResult($status, $stdout, $stderr);
    }

    /** Missing hosted-Linux isolation support fails before launching anything; there is no weaker fallback. */
    private function assertPrerequisites(float $timeout): void
    {
        if (!is_finite($timeout) || $timeout <= 0 || $timeout > self::TIMEOUT_SECONDS) {
            throw new InvalidArgumentException('Optional analyzer timeout must be positive, finite and at most 300 seconds.');
        }
        if (
            'Linux' !== PHP_OS_FAMILY
            || !is_executable('/usr/bin/setsid')
            || !function_exists('posix_getpgid')
            || !function_exists('posix_getsid')
            || !function_exists('posix_kill')
        ) {
            throw new RuntimeException('Optional workflow execution requires hosted Linux, setsid and PHP POSIX process-group support.');
        }
    }

    /** The trusted launcher blocks before payload execution until its own PID/session/group have been checked. */
    private function confirmGroup($control, int $pid, int $deadline): void
    {
        stream_set_blocking($control, false);
        $identity = '';
        while (!str_contains($identity, "\n")) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Owned analyzer session handshake timed out.');
            }
            $bytes = fread($control, 32);
            if (false === $bytes || ('' === $bytes && feof($control))) {
                throw new RuntimeException('Owned analyzer session handshake failed.');
            }
            $identity .= $bytes;
            if (strlen($identity) > 32) {
                throw new RuntimeException('Owned analyzer session identity is invalid.');
            }
            usleep(1000);
        }
        if ($identity !== $pid . "\n" || @posix_getpgid($pid) !== $pid || @posix_getsid($pid) !== $pid) {
            throw new RuntimeException('Owned analyzer process group could not be confirmed.');
        }
    }

    /**
     * Reads fixed chunks fairly from both channels and checks the deadline between reads.
     *
     * @param resource $process The exclusively owned trusted launcher resource.
     * @param array<int, resource> $pipes Nonblocking stdout/stderr pipes, closed as EOF is observed.
     * @return array{string, string, int|null} Exact bounded channels and the observed completion status.
     */
    private function read($process, array &$pipes, int $deadline): array
    {
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        $captured = [1 => '', 2 => ''];
        $status = null;
        do {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Optional analyzer exceeded its total invocation timeout; no clean result is available.');
            }
            $ready = array_values($pipes);
            $write = $except = [];
            if ([] !== $ready && false === @stream_select($ready, $write, $except, 0, 100000)) {
                throw new RuntimeException('Cannot read optional-analyzer channels.');
            }
            foreach ($ready as $pipe) {
                $channel = array_search($pipe, $pipes, true);
                if (false === $channel) {
                    throw new RuntimeException('Optional analyzer channel identity is unavailable.');
                }
                $limit = 1 === $channel ? self::REPORT_BYTES : self::STDERR_BYTES;
                $bytes = fread($pipe, min(self::CHUNK_BYTES, $limit - strlen($captured[$channel]) + 1));
                if (false === $bytes || strlen($captured[$channel]) + strlen($bytes) > $limit) {
                    throw new RuntimeException('Optional analyzer output exceeded its capture limit; the incomplete report is not classified.');
                }
                $captured[$channel] .= $bytes;
                if (feof($pipe)) {
                    fclose($pipe);
                    unset($pipes[$channel]);
                }
            }
            $state = proc_get_status($process);
            if (!$state['running']) {
                $status = $state['signaled'] ? 128 + $state['termsig'] : ($state['exitcode'] >= 0 ? $state['exitcode'] : $status);
            }
            if ([] === $pipes && $state['running']) {
                usleep(10000);
            }
        } while ([] !== $pipes || $state['running']);

        return [$captured[1], $captured[2], $status];
    }

    /** Native preflight/cache temporary files belong to this invocation, never the consumer or a shared cache. */
    private function temporary(): string
    {
        $base = realpath(getenv('RUNNER_TEMP') ?: sys_get_temp_dir());
        if (false === $base) {
            throw new RuntimeException('Runner temporary directory is unavailable.');
        }
        $path = $base . '/standards-optional-' . bin2hex(random_bytes(12));
        if (!mkdir($path, 0700)) {
            throw new RuntimeException('Cannot create owned analyzer temporary directory.');
        }

        return $path;
    }

    /** Traverses only the freshly owned directory and never follows links created by executable configuration. */
    private function removeTemporary(string $directory, int &$remaining = 4096): void
    {
        if (is_link($directory) || is_file($directory)) {
            if (!unlink($directory)) {
                throw new RuntimeException('Cannot remove the owned temporary path replacement.');
            }

            return;
        }
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
            if (--$remaining < 0) {
                throw new RuntimeException('Owned analyzer temporary cleanup exceeded its entry bound; runner teardown must remove the remainder.');
            }
            if ($file->isLink() || !$file->isDir()) {
                if (!unlink($file->getPathname())) {
                    throw new RuntimeException('Cannot remove an owned analyzer temporary entry.');
                }
            } else {
                $this->removeTemporary($file->getPathname(), $remaining);
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Cannot remove the owned analyzer temporary directory.');
        }
    }
}
