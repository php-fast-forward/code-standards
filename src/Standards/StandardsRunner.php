<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

use FastForward\CodeStandards\Process\ProcessRunner;

/** Owns style versus syntax policy; no dependency operations occur here. */
final class StandardsRunner
{
    /**
     * @param ProcessRunner $process Existing transport and its subprocess environment policy.
     * @param string $worker Absolute internal worker entrypoint from this package.
     * @param string $autoload Composer autoloader of this tool's own installation.
     * @param string $engine Absolute installed ECS package directory, resolved before consumer config runs.
     */
    public function __construct(
        private readonly ProcessRunner $process,
        private readonly string $worker,
        private readonly string $autoload,
        private readonly string $engine,
    ) {}

    /**
     * Checks syntax before style; only explicit fix permits ECS source writes.
     *
     * @param list<string> $files Validated absolute PHP paths.
     */
    public function run(string $directory, array $files, string $config, bool $fix): int
    {
        if ([] === $files) {
            return 0;
        }
        foreach ($files as $file) {
            if (0 !== $status = $this->process->run([PHP_BINARY, '-n', '-l', $file], $directory)) {
                return $status;
            }
        }

        // Bounded batches avoid ARG_MAX. Stop at the first failure; never report partial success.
        foreach (array_chunk($files, 50) as $batch) {
            $status = $this->process->run($this->formatterCommand($config, $fix, $batch), $directory);
            if (0 !== $status) {
                return $status;
            }
            if ($fix && 0 !== $status = $this->process->run($this->formatterCommand($config, false, $batch), $directory)) {
                return $status;
            }
        }

        return 0;
    }

    /**
     * @param list<string> $files Absolute selected paths, retained as separate process arguments.
     * @return list<string> Fixed internal protocol fields followed by separate source paths.
     */
    private function formatterCommand(string $config, bool $fix, array $files): array
    {
        return [PHP_BINARY, $this->worker, $this->autoload, $this->engine, $config, $fix ? '--fix' : '--check', '--', ...$files];
    }
}
