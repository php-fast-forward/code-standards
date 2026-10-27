<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

/** Builds the shared literal-argument protocol for the isolated ECS worker. */
final class EcsWorkerCommand
{
    /**
     * Builds the shared worker invocation without shell expansion.
     *
     * @param list<string> $files Absolute source paths, never shell-expanded.
     * @return list<string> Explicit installation, mode, format and selected-file arguments.
     */
    public static function build(string $worker, string $autoload, string $engine, string $config, bool $fix, array $files, string $format = 'text'): array
    {
        $formatArguments = 'text' === $format ? [] : ['--format=' . $format];

        return [PHP_BINARY, $worker, $autoload, $engine, $config, $fix ? '--fix' : '--check', ...$formatArguments, '--', ...$files];
    }
}
