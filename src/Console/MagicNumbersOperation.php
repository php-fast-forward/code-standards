<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Analysis\MagicNumbersRunner;
use FastForward\CodeStandards\Console\Input\MagicNumbersInput;
use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Project\ProjectDirectoryResolver;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Selects the existing Git/PHP scope without resolving or executing consumer ECS configuration. */
final readonly class MagicNumbersOperation
{
    /** Captures shared directory/selection policy and the separate read-only analysis boundary. */
    public function __construct(
        private ProjectDirectoryResolver $directories,
        private FileSelection $selection,
        private PhpFiles $phpFiles,
        private MagicNumbersRunner $analysis,
    ) {}

    /** Returns native findings status, or INVALID for controlled validation/execution failures. */
    public function run(MagicNumbersInput $settings, SymfonyStyle $io): int
    {
        try {
            $directory = $this->directories->resolve($settings->all, $settings->base, $settings->head, $settings->directory);
            $paths = $this->selection->select($directory, $settings->base, $settings->head);
            $files = $this->phpFiles->filter($directory, $paths);

            return $this->analysis->run($files, $settings->format, $settings->ignoreNumbers, $settings->ignoreFuncs, $settings->extensions);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $io->getErrorStyle()->error($exception->getMessage());

            return Command::INVALID;
        }
    }
}
