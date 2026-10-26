<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Console\Input\ScanInput;
use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Standards\StandardsRunner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Shares bounded scan orchestration between explicit check and fix entrypoints. */
final readonly class StandardsOperation
{
    /** Captures validation, selection, filtering and execution boundaries without resolving project state. */
    public function __construct(
        private ProjectScopeResolver $projects,
        private FileSelection $selection,
        private PhpFiles $phpFiles,
        private StandardsRunner $standards,
    ) {}

    /**
     * Resolves and presents one selection, preserving tool status and rendering controlled errors to stderr.
     *
     * @param ScanInput $settings Native mapped values, validated before any tool execution.
     * @param SymfonyStyle $io Invocation-local output and error style supplied by Symfony.
     * @param bool $fix Explicit authority granted only by the fix entrypoint.
     * @return int Exact tool status, or INVALID for controlled input/operation errors.
     */
    public function run(ScanInput $settings, SymfonyStyle $io, bool $fix): int
    {
        try {
            $scope = $this->projects->resolve($settings->all, $settings->base, $settings->head, $settings->directory, $settings->config);
            $paths = $this->selection->select($scope->directory, $scope->base, $scope->head, $scope->config);
            $files = $this->phpFiles->filter($scope->directory, $paths);
            $io->writeln(sprintf('Selected %d PHP file(s).', count($files)));

            return $this->standards->run($scope->directory, $files, $scope->config, $fix);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $io->getErrorStyle()->error($exception->getMessage());

            return Command::INVALID;
        }
    }
}
