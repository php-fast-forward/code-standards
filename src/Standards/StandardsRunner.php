<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

use FastForward\CodeStandards\Process\ProcessRunner;

/** Owns style versus syntax policy; no dependency operations occur here. */
final class StandardsRunner
{
    /** @param string $ecs Absolute ECS entrypoint from this tool's installation. */
    public function __construct(private readonly ProcessRunner $process, private readonly string $ecs)
    {
    }

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
            $check = [PHP_BINARY, $this->ecs, 'check', '--config=' . $config, '--no-progress-bar', '--no-interaction'];
            $command = $check;
            if ($fix) {
                $command[] = '--fix';
            }
            $status = $this->process->run([...$command, '--', ...$batch], $directory);
            if (0 !== $status) {
                return $status;
            }
            if ($fix && 0 !== $status = $this->process->run([...$check, '--', ...$batch], $directory)) {
                return $status;
            }
        }

        return 0;
    }
}
