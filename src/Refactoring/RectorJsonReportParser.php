<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Refactoring;

use FastForward\CodeStandards\Process\ProcessResult;
use InvalidArgumentException;
use JsonException;

/** Validates native Rector preview reports without applying workflow policy or rendering diagnostics. */
final class RectorJsonReportParser
{
    /**
     * Requires complete native data whose totals, locations and exit agree.
     *
     * @return array<string,mixed> The decoded native report, retaining all fields and native ordering.
     * @throws InvalidArgumentException When the report is malformed or inconsistent with its completed exit.
     */
    public function parse(ProcessResult $process): array
    {
        try {
            $report = json_decode(json: $process->stdout, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                message: 'Rector did not return a complete native JSON document.',
                previous: $exception,
            );
        }
        if (!is_array($report) || !isset($report['totals']) || !is_array($report['totals'])) {
            throw new InvalidArgumentException('Rector JSON totals are unavailable.');
        }
        $changes = $this->jsonInteger($report['totals']['changed_files'] ?? null);
        $errorCount = $this->jsonInteger($report['totals']['errors'] ?? null);
        $errors = $this->list($report['errors'] ?? []);
        if ($errorCount !== count($errors)) {
            throw new InvalidArgumentException('Rector error total is inconsistent.');
        }
        $files = $this->list($report['changed_files'] ?? []);
        if (
            count($files) !== $changes
            || count(
                array_filter($files, static fn(mixed $file): bool => is_string($file) && '' !== $file),
            ) !== count(
                $files,
            )
        ) {
            throw new InvalidArgumentException('Rector changed-file list is inconsistent.');
        }
        $this->validateErrors($errors);
        $this->validateChanges($this->list($report['file_diffs'] ?? []), $files);
        if (0 !== $errorCount) {
            if (1 !== $process->exitCode) {
                throw new InvalidArgumentException('Rector error report contradicts its exit.');
            }

            return $report;
        }
        if ((0 === $changes && 0 !== $process->exitCode) || (0 !== $changes && 2 !== $process->exitCode)) {
            throw new InvalidArgumentException('Rector preview exit contradicts its native totals.');
        }

        return $report;
    }

    /** Native JSON counts must be integers rather than coerced strings or floating point values. */
    private function jsonInteger(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException('Rector JSON count is invalid.');
        }

        return $value;
    }

    /** @return list<mixed> Native ordered report entries, not an arbitrary keyed JSON object. */
    private function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('Rector JSON entries must be a list.');
        }

        return $value;
    }

    /** @param list<mixed> $errors Native error locations; absent locations remain absent. */
    private function validateErrors(array $errors): void
    {
        foreach ($errors as $error) {
            if (
                !is_array($error)
                || (isset($error['file'])
                    && !is_string($error['file']))
                || !is_string($error['message'] ?? null)
                || (isset($error['line'])
                    && (!is_int($error['line'])
                        || $error['line'] < 1))
            ) {
                throw new InvalidArgumentException('Rector JSON error location is invalid.');
            }
        }
    }

    /**
     * Validates native change records without inferring source locations from diff text.
     *
     * @param list<mixed> $changes Optional native file diffs.
     * @param list<string> $files Changed paths reported by the same native invocation.
     */
    private function validateChanges(array $changes, array $files): void
    {
        foreach ($changes as $change) {
            if (
                !is_array($change)
                || !is_string($change['file'] ?? null)
                || !in_array(
                    $change['file'],
                    $files,
                    true,
                )
                || !is_string(
                    $change['diff'] ?? null,
                )
            ) {
                throw new InvalidArgumentException('Rector JSON change location is invalid.');
            }
        }
    }
}
