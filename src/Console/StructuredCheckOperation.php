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
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;
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
    ) {}

    /** Resolves one selection and writes exactly one JSON document after result classification. */
    public function run(ScanInput $settings, SymfonyStyle $io): int
    {
        try {
            $scope = $this->projects->resolve($settings->all, $settings->base, $settings->head, $settings->directory, $settings->config);
            $paths = $this->selection->select($scope->directory, $scope->base, $scope->head, $scope->config);
            $files = $this->phpFiles->filter($scope->directory, $paths);
            $result = $this->standards->run($scope->directory, $files, $scope->config);
        } catch (Throwable $exception) {
            $result = new CheckResult('operational-error', $exception instanceof InvalidArgumentException ? 2 : 1, false, 0, 0, 0, errors: [
                ['phase' => 'selection', 'message' => $exception->getMessage()],
            ]);
        }

        return $this->present($result, $io);
    }

    /** Rejects unrepresentable byte strings without silently replacing paths or truncating a report. */
    private function present(CheckResult $result, SymfonyStyle $io): int
    {
        try {
            $json = json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $result = new CheckResult('operational-error', 1, false, $result->selected, $result->syntaxChecked, $result->styleSubmitted, errors: [
                ['phase' => 'encoding', 'message' => 'Check output contains bytes that cannot be represented as JSON.'],
            ]);
            $json = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        }
        if ([] !== $result->errors) {
            $io->getErrorStyle()->writeln('Code Standards check did not complete successfully.', OutputInterface::OUTPUT_RAW);
        }
        if ('' !== $result->stderr) {
            $io->getErrorStyle()->write($result->stderr, false, OutputInterface::OUTPUT_RAW);
        }
        $io->writeln($json, OutputInterface::OUTPUT_RAW);

        return $result->exitCode;
    }
}
