<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Console\Input\ScanInput;
use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Standards\CheckResult;
use FastForward\CodeStandards\Standards\StructuredCheckRunner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** Presents one explicitly selected JSON check without human stdout or implicit source writes. */
final readonly class StructuredCheckOperation
{
    /** Injects the same selection policy as text mode and a nonstreaming check runner. */
    public function __construct(
        private ProjectScopeResolver $projects,
        private FileSelection $selection,
        private PhpFiles $phpFiles,
        private StructuredCheckRunner $standards,
        private CheckResultRenderer $renderer,
    ) {}

    /** Resolves one selection and writes exactly one JSON document after result classification. */
    public function run(ScanInput $settings, SymfonyStyle $io, bool $pretty = false): int
    {
        try {
            $scope = $this->projects->resolve($settings->all, $settings->base, $settings->head, $settings->directory, $settings->config);
            $paths = $this->selection->select($scope->directory, $scope->base, $scope->head, $scope->config);
            $files = $this->phpFiles->filter($scope->directory, $paths);
            $result = $this->standards->run($scope->directory, $files, $scope->config);
        } catch (Throwable $exception) {
            $exitCode = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException ? 2 : 1;
            $result = new CheckResult('operational-error', $exitCode, false, 0, 0, 0, errors: [
                ['phase' => 'selection', 'message' => $exception->getMessage()],
            ]);
        }

        return $this->renderer->render($result, $io, $pretty);
    }
}
