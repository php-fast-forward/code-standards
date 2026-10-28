<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Input;

use Symfony\Component\Console\Attribute\Option;

/** Explicit opt-in analysis inputs; no formatter configuration or write mode is accepted. */
final class MagicNumbersInput
{
    #[Option(description: 'Analyze all tracked PHP files.')]
    public bool $all = false;

    #[Option(description: 'Immutable base commit for changed-file selection.')]
    public ?string $base = null;

    #[Option(description: 'Immutable head commit matching the checkout.')]
    public ?string $head = null;

    #[Option(description: 'Project Git checkout root; defaults to the current directory.', shortcut: 'C')]
    public ?string $directory = null;

    #[Option(description: 'Output format: text or deterministic native XML on stdout.')]
    public string $format = 'text';

    #[Option(description: 'Native comma-separated additions to PHPMND fixed numeric exceptions 0,1.')]
    public string $ignoreNumbers = '0,1';

    #[Option(description: 'Native comma-separated function exceptions for the argument extension.')]
    public ?string $ignoreFuncs = null;

    #[Option(description: 'Native analysis contexts, not file suffixes: e.g. all,-argument or assign.')]
    public ?string $extensions = null;
}
