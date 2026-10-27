<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

use FastForward\CodeStandards\Process\LimitedResultProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use Throwable;

/** Captures read-only syntax and native ECS results without streaming onto JSON stdout. */
final readonly class StructuredCheckRunner
{
    /** Installation paths are resolved from the tool before executable consumer configuration runs. */
    public function __construct(
        private ProcessRunner $process,
        private string $worker,
        private string $autoload,
        private string $engine,
        private EcsJsonReportParser $reports,
    ) {}

    /**
     * @param list<string> $files Validated absolute PHP paths.
     * @return CheckResult Complete findings or an honest partial result; no source-write authority is granted.
     */
    public function run(string $directory, array $files, string $config): CheckResult
    {
        if ([] === $files) {
            return new CheckResult('clean', 0, true, 0, 0, 0);
        }

        $syntaxChecked = $styleSubmitted = 0;
        $findings = [];
        $phase = 'syntax';
        $stderr = '';

        try {
            if (!$this->process instanceof LimitedResultProcessRunner) {
                throw new \RuntimeException('JSON checking requires limited result capture.');
            }
            foreach ($files as $file) {
                $result = $this->process->captureLimitedResult([PHP_BINARY, '-n', '-l', $file], $directory, 65_536, 65_536, 300);
                $stderr = $this->appendDiagnostic($stderr, $result->stderr);
                ++$syntaxChecked;
                if (0 !== $result->exitCode) {
                    return new CheckResult('syntax-failed', $result->exitCode, false, count($files), $syntaxChecked, 0, errors: [
                        ['phase' => 'syntax', 'file' => $file, 'stdout' => $result->stdout, 'stderr' => $result->stderr],
                    ], stderr: $stderr);
                }
            }

            $phase = 'ecs';
            foreach (array_chunk($files, 50) as $batch) {
                $command = EcsWorkerCommand::build($this->worker, $this->autoload, $this->engine, $config, false, $batch, 'json');
                $result = $this->process->captureLimitedResult($command, $directory, 2_097_152, 65_536, 300);
                $stderr = $this->appendDiagnostic($stderr, $result->stderr);
                $report = $this->reports->parse($result, $batch);
                $styleSubmitted += count($batch);
                $findings += $report['files'];
                if (0 !== $result->exitCode) {
                    return new CheckResult('findings', 2, count($files) === $styleSubmitted, count($files), $syntaxChecked, $styleSubmitted, $findings, stderr: $stderr);
                }
            }

            return new CheckResult('clean', 0, true, count($files), $syntaxChecked, $styleSubmitted, stderr: $stderr);
        } catch (Throwable $exception) {
            return new CheckResult('operational-error', 1, false, count($files), $syntaxChecked, $styleSubmitted, $findings, [
                ['phase' => $phase, 'message' => $exception->getMessage()],
            ], $stderr);
        }
    }

    /** Keeps retained diagnostics bounded across the entire check without discarding warnings. */
    private function appendDiagnostic(string $previous, string $current): string
    {
        if (strlen($current) > 65_536 - strlen($previous)) {
            throw new \OverflowException('Combined check diagnostics exceeded their byte limit.');
        }

        return $previous . $current;
    }
}
