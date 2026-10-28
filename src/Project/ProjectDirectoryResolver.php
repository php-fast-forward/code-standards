<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Project;

use InvalidArgumentException;

/** Validates the shared scan mode and directory without loading tool-specific configuration. */
final class ProjectDirectoryResolver
{
    /**
     * Resolves exactly one full or changed-file scan against an existing directory.
     *
     * @throws InvalidArgumentException When mode selection or the project directory is invalid.
     */
    public function resolve(bool $all, ?string $base, ?string $head, ?string $directory): string
    {
        if ($all === (null !== $base || null !== $head) || (!$all && (null === $base || null === $head))) {
            throw new InvalidArgumentException('Select exactly --all or both --base and --head.');
        }

        $project = $directory ?? getcwd();
        $root = is_string($project) && '' !== $project ? realpath($project) : false;
        if (false === $root || !is_dir($root)) {
            throw new InvalidArgumentException('Invalid project directory.');
        }

        return $root;
    }
}
