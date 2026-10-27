<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

use FastForward\CodeStandards\Process\ProcessResult;
use JsonException;
use RuntimeException;

/** Validates the native ECS report before treating completed output as style findings. */
final class EcsJsonReportParser
{
    /**
     * @param list<string> $selected Exact paths supplied to this worker invocation.
     * @return array{errors:int,diffs:int,files:array<string,array<string,mixed>>} Consistent native findings, never omitted system errors.
     * @throws RuntimeException When status, schema, paths or totals cannot establish a complete report.
     */
    public function parse(ProcessResult $result, array $selected): array
    {
        try {
            $report = json_decode($result->stdout, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('ECS did not return one valid JSON report.', previous: $exception);
        }

        if (!is_array($report) || !$this->hasKeys($report, ['totals', 'files'])
            || !is_array($report['totals']) || !$this->hasKeys($report['totals'], ['errors', 'diffs'])
            || !is_int($report['totals']['errors']) || $report['totals']['errors'] < 0
            || !is_int($report['totals']['diffs']) || $report['totals']['diffs'] < 0
            || !is_array($report['files'])) {
            throw new RuntimeException('ECS returned an unsupported JSON report schema.');
        }

        $errors = $diffs = 0;
        foreach ($report['files'] as $path => $file) {
            if (!is_string($path) || !in_array($path, $selected, true) || !is_array($file) || [] === $file
                || [] !== array_diff(array_keys($file), ['errors', 'diffs'])) {
                throw new RuntimeException('ECS returned an unexpected report path or file record.');
            }
            foreach ($file as $kind => $records) {
                if (!is_array($records) || !array_is_list($records) || [] === $records) {
                    throw new RuntimeException('ECS returned an invalid findings list.');
                }
                foreach ($records as $record) {
                    $this->validateRecord($kind, $record, $path);
                }
            }
            $errors += count($file['errors'] ?? []);
            $diffs += count($file['diffs'] ?? []);
        }

        if ($errors !== $report['totals']['errors'] || $diffs !== $report['totals']['diffs']) {
            throw new RuntimeException('ECS totals include unreported or inconsistent findings; execution may have failed.');
        }
        $expected = 0 === $errors + $diffs ? 0 : 2;
        if ($expected !== $result->exitCode) {
            throw new RuntimeException('ECS exit status does not agree with its JSON report.');
        }

        return ['errors' => $errors, 'diffs' => $diffs, 'files' => $report['files']];
    }

    /** Rejects malformed diagnostic records without parsing human messages or diffs. */
    private function validateRecord(string $kind, mixed $record, string $path): void
    {
        if (!is_array($record)) {
            throw new RuntimeException('ECS returned an invalid finding record.');
        }
        if ('errors' === $kind) {
            if (!$this->hasKeys($record, ['line', 'file_path', 'message', 'source_class'])
                || !is_int($record['line']) || $record['line'] < 1 || $path !== $record['file_path']
                || !is_string($record['message']) || !is_string($record['source_class']) || '' === $record['source_class']) {
                throw new RuntimeException('ECS returned an invalid diagnostic record.');
            }

            return;
        }
        if (!$this->hasKeys($record, ['diff', 'applied_checkers']) || !is_string($record['diff'])
            || !is_array($record['applied_checkers']) || !array_is_list($record['applied_checkers']) || [] === $record['applied_checkers']) {
            throw new RuntimeException('ECS returned an invalid diff record.');
        }
        foreach ($record['applied_checkers'] as $checker) {
            if (!is_string($checker) || '' === $checker) {
                throw new RuntimeException('ECS returned an invalid checker identity.');
            }
        }
    }

    /** Validates native object fields without treating JSON member order as significant. */
    private function hasKeys(array $record, array $expected): bool
    {
        return count($record) === count($expected) && [] === array_diff(array_keys($record), $expected);
    }
}
