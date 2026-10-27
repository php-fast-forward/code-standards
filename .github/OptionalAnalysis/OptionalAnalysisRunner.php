<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use InvalidArgumentException;
use Throwable;

/** Coordinates the two existing optional analyzers without changing the mandatory formatting check. */
final readonly class OptionalAnalysisRunner
{
    /** Captures existing selection/config boundaries and the installation-owned native CLI. */
    public function __construct(
        private FileSelection $selection,
        private PhpFiles $phpFiles,
        private ProjectScopeResolver $projects,
        private BoundedProcess $process,
        private NativeReportParser $reports,
        private string $root,
    ) {}

    /** @return list<AnalysisResult> A result exists for both tools, including disabled/error paths. */
    public function run(WorkflowInput $input): array
    {
        $magic = new AnalysisResult('Magic Numbers', 'disabled', details: 'Not enabled; no analyzer executed.');
        $rector = new AnalysisResult('Rector', 'disabled', details: 'Not enabled; no analyzer executed.');
        if (!$input->magicNumbers && !$input->rector) {
            return [$magic, $rector];
        }
        try {
            $directory = realpath($input->directory);
            if (false === $directory || !is_dir($directory)) {
                throw new InvalidArgumentException('Consumer directory is unavailable.');
            }
        } catch (Throwable $exception) {
            return [
                $input->magicNumbers ? $this->failure('Magic Numbers', $exception) : $magic,
                $input->rector ? $this->failure('Rector', $exception) : $rector,
            ];
        }
        if ($input->magicNumbers) {
            $magic = $this->magicNumbers($input, $directory);
        }
        if ($input->rector) {
            $rector = $this->rector($input, $directory);
        }

        return [$magic, $rector];
    }

    /** Selects only Magic Numbers' eligible paths using the exact-PR/ignore contract. */
    private function magicNumbers(WorkflowInput $input, string $directory): AnalysisResult
    {
        $files = null;
        $result = null;
        try {
            $paths = $this->selection->select($directory, $input->base, $input->head);
            $files = $this->phpFiles->filter($directory, $paths);
            if ([] === $files) {
                return new AnalysisResult('Magic Numbers', 'not-executed', 0, details: 'No eligible changed PHP files; the engine was not executed.');
            }
            $result = $this->process->capture([
                PHP_BINARY, '-d', 'memory_limit=256M', $this->root . '/bin/code-standards', 'magic-numbers',
                '--directory=' . $directory, '--base=' . $input->base, '--head=' . $input->head, '--format=xml',
            ], $directory);

            return $this->reports->magicNumbers($result, $files);
        } catch (Throwable $exception) {
            return $this->failure('Magic Numbers', $exception, null === $files ? null : count($files), $result);
        }
    }

    /** Rector retains explicitly configured whole-project paths and always runs preview, never apply. */
    private function rector(WorkflowInput $input, string $directory): AnalysisResult
    {
        $result = null;
        try {
            if ('' === $input->rectorConfig || str_starts_with($input->rectorConfig, '/')) {
                throw new InvalidArgumentException('Enabled Rector requires a consumer-relative rector-config input.');
            }
            $scope = $this->projects->resolve(true, null, null, $directory, $input->rectorConfig);
            if (!str_starts_with($scope->config, $directory . '/')) {
                throw new InvalidArgumentException('Workflow Rector configuration must resolve inside the consumer checkout.');
            }
            $result = $this->process->capture([
                PHP_BINARY, '-d', 'memory_limit=256M', $this->root . '/bin/code-standards', 'refactor',
                '--directory=' . $directory, '--config=' . $scope->config, '--format=json',
            ], $directory);

            return $this->reports->rector($result);
        } catch (Throwable $exception) {
            return $this->failure('Rector', $exception, process: $result);
        }
    }

    /** Exceptions never turn into advisory findings or an unavailable zero count. */
    private function failure(string $tool, Throwable $exception, ?int $selected = null, ?ProcessResult $process = null): AnalysisResult
    {
        $details = $exception->getMessage();
        if (null !== $process && '' !== $process->stderr) {
            $details .= "\nNative stderr: " . $process->stderr;
        }

        return new AnalysisResult($tool, 'operational-error', $selected, details: $details, nativeExit: $process?->exitCode);
    }
}
