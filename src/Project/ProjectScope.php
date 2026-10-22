<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Project;

/** Resolved project/config paths and optional immutable changed-file selectors. */
final readonly class ProjectScope
{
    /**
     * Carries validated project paths without reading files or parsing console options.
     *
     * @param string $directory Existing absolute project directory.
     * @param string $config Existing absolute ECS configuration file.
     * @param string|null $base Immutable base selector, absent for a full scan.
     * @param string|null $head Immutable head selector, absent for a full scan.
     */
    public function __construct(
        public string $directory,
        public string $config,
        public ?string $base,
        public ?string $head,
    ) {
    }
}
