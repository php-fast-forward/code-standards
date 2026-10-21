<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Standards\StandardsRunner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Orchestrates one fixed check/fix operation using native Symfony option binding and injected services. */
final class StandardsCommand extends Command
{
    /**
     * Binds operation authority and collaborators; construction performs no project reads or tool execution.
     *
     * @param bool $fix Whether this registered operation may pass the ECS write flag.
     * @param ProjectScopeResolver $projects Validates mode and project/config paths.
     * @param FileSelection $selection Resolves tracked files and exact Git ranges.
     * @param PhpFiles $phpFiles Applies the supported PHP source boundary.
     * @param StandardsRunner $standards Executes syntax/style tools and preserves their status.
     */
    public function __construct(
        private readonly bool $fix,
        private readonly ProjectScopeResolver $projects,
        private readonly FileSelection $selection,
        private readonly PhpFiles $phpFiles,
        private readonly StandardsRunner $standards,
    ) {
        parent::__construct($fix ? 'fix' : 'check');
    }

    /** Declares only supported options; Symfony owns help, global flags and argument parsing. */
    protected function configure(): void
    {
        $this->setDescription($this->fix ? 'Explicitly apply ECS style fixes after syntax validation.' : 'Check PHP syntax and style without changing source.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Check all tracked PHP files.')
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Immutable base commit for changed-file selection.')
            ->addOption('head', null, InputOption::VALUE_REQUIRED, 'Immutable head commit matching the checkout.')
            ->addOption('directory', 'C', InputOption::VALUE_REQUIRED, 'Project Git checkout root; defaults to the current directory.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Explicit ECS config file; defaults to the packaged preset.');
    }

    /**
     * Presents selection and controlled errors while domain services own validation, paths and execution.
     *
     * @return int Exact tool status, success for no selected files, or INVALID for controlled input/operation errors.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $scope = $this->projects->resolve(
                $input->getOption('all'),
                $input->getOption('base'),
                $input->getOption('head'),
                $input->getOption('directory'),
                $input->getOption('config'),
            );

            $paths = $this->selection->select($scope->directory, $scope->base, $scope->head, $scope->config);
            $files = $this->phpFiles->filter($scope->directory, $paths);
            $output->writeln(sprintf('Selected %d PHP file(s).', count($files)));

            return $this->standards->run($scope->directory, $files, $scope->config, $this->fix);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            (new SymfonyStyle($input, $errors))->error($exception->getMessage());

            return self::INVALID;
        }
    }
}
