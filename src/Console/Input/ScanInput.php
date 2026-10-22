<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Input;

use Symfony\Component\Console\Attribute\Option;

/** Native mapped input; ProjectScopeResolver validates the selected mode and paths before execution. */
final class ScanInput
{
    #[Option(description: 'Check all tracked PHP files.')]
    public bool $all = false;

    #[Option(description: 'Immutable base commit for changed-file selection.')]
    public ?string $base = null;

    #[Option(description: 'Immutable head commit matching the checkout.')]
    public ?string $head = null;

    #[Option(description: 'Project Git checkout root; defaults to the current directory.', shortcut: 'C')]
    public ?string $directory = null;

    #[Option(description: 'Explicit ECS config file; defaults to the packaged preset.')]
    public ?string $config = null;
}
