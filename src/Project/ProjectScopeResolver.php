<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Project;

use InvalidArgumentException;

/** Resolves explicit project settings; Git identity validation stays with FileSelection. */
final readonly class ProjectScopeResolver
{
    /** Injects the packaged preset location; resolution never discovers a competing consumer preset implicitly. */
    public function __construct(private string $defaultConfig) {}

    /**
     * Requires exactly one scan mode and existing project/config paths, without writing or loading config PHP.
     *
     * @throws InvalidArgumentException When mode selection, directory or configuration is invalid.
     */
    public function resolve(bool $all, ?string $base, ?string $head, ?string $directory, ?string $config): ProjectScope
    {
        if ($all === (null !== $base || null !== $head) || (!$all && (null === $base || null === $head))) {
            throw new InvalidArgumentException('Select exactly --all or both --base and --head.');
        }

        $project = $directory ?? getcwd();
        $root = is_string($project) && '' !== $project ? realpath($project) : false;
        if (false === $root || !is_dir($root)) {
            throw new InvalidArgumentException('Invalid project directory.');
        }

        $configuration = $this->configuration($root, $config);

        return new ProjectScope($root, $configuration, $base, $head);
    }

    /**
     * Resolves a selected file relative to the project or uses the injected default.
     *
     * @throws InvalidArgumentException When the selected configuration cannot be a readable regular file.
     */
    private function configuration(string $directory, ?string $config): string
    {
        $selected = $config ?? $this->defaultConfig;
        if ('' === $selected) {
            throw new InvalidArgumentException('Configuration path must not be empty.');
        }

        $path = str_starts_with($selected, '/') ? $selected : $directory . '/' . $selected;
        $resolved = realpath($path);
        if (false === $resolved || !is_file($resolved) || !is_readable($resolved)) {
            throw new InvalidArgumentException('Configuration file does not exist or is not readable.');
        }

        return $resolved;
    }
}
