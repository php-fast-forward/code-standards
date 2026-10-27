<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use DOMDocument;
use DOMElement;
use FastForward\CodeStandards\Process\ProcessResult;
use InvalidArgumentException;
use JsonException;

/** Classifies qualified native schemas together with their exits; human output is never scraped. */
final class NativeReportParser
{
    /**
     * Requires complete, consistent native XML before accepting exit 1 as findings.
     *
     * @param list<string> $selectedFiles Exact absolute files from the existing Git/PHP selector.
     * @throws InvalidArgumentException When output, counts, paths or status contradict the qualified schema.
     */
    public function magicNumbers(ProcessResult $process, array $selectedFiles): AnalysisResult
    {
        $root = $this->xml($process->stdout);
        $reportedFiles = $this->children($root, 'files');
        if (1 !== count($reportedFiles)) {
            throw new InvalidArgumentException('PHPMND XML must contain exactly one files element.');
        }
        $allowed = array_fill_keys($selectedFiles, true);
        $diagnostics = [];
        $seen = [];
        foreach ($this->children($reportedFiles[0], 'file') as $file) {
            $path = $file->getAttribute('path');
            if (!isset($allowed[$path]) || isset($seen[$path])) {
                throw new InvalidArgumentException('PHPMND reported an unexpected or repeated selected path.');
            }
            $seen[$path] = true;
            $entries = $this->children($file, 'entry');
            if ([] === $entries || count($entries) !== $this->integer($file->getAttribute('errors'))) {
                throw new InvalidArgumentException('PHPMND file finding count is inconsistent.');
            }
            foreach ($entries as $entry) {
                $line = $this->integer($entry->getAttribute('line'));
                if ($line < 1) {
                    throw new InvalidArgumentException('PHPMND finding line must be positive.');
                }
                $diagnostics[] = ['file' => $path, 'line' => $line, 'message' => 'Numeric literal requires review.'];
            }
        }
        $count = $this->integer($root->getAttribute('errorCount'));
        if ($count !== count($diagnostics) || count($seen) !== $this->integer($root->getAttribute('fileCount'))) {
            throw new InvalidArgumentException('PHPMND report totals are inconsistent.');
        }
        if ((0 === $count && 0 !== $process->exitCode) || (0 !== $count && 1 !== $process->exitCode)) {
            throw new InvalidArgumentException('PHPMND exit contradicts its complete native report.');
        }

        return new AnalysisResult('Magic Numbers', 0 === $count ? 'clean' : 'findings', count($selectedFiles), $count, $diagnostics, $process->stderr, $process->exitCode);
    }

    /** Native Rector errors remain blocking even when the same report also proposes changes. */
    public function rector(ProcessResult $process): AnalysisResult
    {
        if (strlen($process->stdout) > BoundedProcess::REPORT_BYTES) {
            throw new InvalidArgumentException('Rector native report exceeds the supported size.');
        }
        try {
            $report = json_decode($process->stdout, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Rector did not return a complete native JSON document.', previous: $exception);
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
        if (count($files) !== $changes || count(array_filter($files, static fn(mixed $file): bool => is_string($file) && '' !== $file)) !== count($files)) {
            throw new InvalidArgumentException('Rector changed-file list is inconsistent.');
        }
        $diagnostics = [...$this->rectorErrors($errors), ...$this->rectorChanges($this->list($report['file_diffs'] ?? []), $files)];
        if (0 !== $errorCount) {
            if (1 !== $process->exitCode) {
                throw new InvalidArgumentException('Rector error report contradicts its exit.');
            }

            return new AnalysisResult('Rector', 'operational-error', findingCount: $changes, diagnostics: $diagnostics, details: $errorCount . " native processing error(s).\n" . $process->stderr, nativeExit: $process->exitCode);
        }
        if ((0 === $changes && 0 !== $process->exitCode) || (0 !== $changes && 2 !== $process->exitCode)) {
            throw new InvalidArgumentException('Rector preview exit contradicts its native totals.');
        }
        return new AnalysisResult('Rector', 0 === $changes ? 'clean' : 'findings', findingCount: $changes, diagnostics: $diagnostics, details: "Scope is the explicit native configuration, not the formatter changed-file list.\n" . $process->stderr, nativeExit: $process->exitCode);
    }

    /** Parses without external resolution/substitution, then rejects DTDs; CDATA remains ordinary source text. */
    private function xml(string $output): DOMElement
    {
        if (strlen($output) > BoundedProcess::REPORT_BYTES || !extension_loaded('dom') || 1 !== preg_match('//u', $output) || str_contains($output, "\0")) {
            throw new InvalidArgumentException('PHPMND requires UTF-8 native XML without DTD/entity declarations and an available DOM extension.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            $document->validateOnParse = false;
            if (!$document->loadXML($output, LIBXML_NONET) || null !== $document->doctype || 'phpmnd' !== $document->documentElement?->tagName) {
                throw new InvalidArgumentException('PHPMND did not return a complete native XML document.');
            }
            if ('' === $document->documentElement->getAttribute('version')) {
                throw new InvalidArgumentException('PHPMND XML version is unavailable.');
            }

            return $document->documentElement;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return list<DOMElement> Direct structural children only; snippets are not interpreted as XML schema. */
    private function children(DOMElement $parent, string $name): array
    {
        $elements = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($name !== $child->tagName) {
                    throw new InvalidArgumentException('Unexpected PHPMND XML structure.');
                }
                $elements[] = $child;
            } elseif (XML_TEXT_NODE === $child->nodeType && '' !== trim($child->textContent)) {
                throw new InvalidArgumentException('Unexpected text in PHPMND XML structure.');
            }
        }

        return $elements;
    }

    /** Exact nonnegative decimal strings avoid coercion, overflow and misleading native counts. */
    private function integer(string $value): int
    {
        $integer = (int) $value;
        if (1 !== preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) || (string) $integer !== $value) {
            throw new InvalidArgumentException('Native report count is invalid.');
        }

        return $integer;
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

    /**
     * Retains actual native error locations; an unavailable path is not replaced with a guessed source file.
     *
     * @param list<mixed> $errors Native error records.
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function rectorErrors(array $errors): array
    {
        $diagnostics = [];
        foreach ($errors as $error) {
            if (!is_array($error)
                || (isset($error['file']) && !is_string($error['file']))
                || !is_string($error['message'] ?? null)
                || (isset($error['line']) && (!is_int($error['line']) || $error['line'] < 1))) {
                throw new InvalidArgumentException('Rector JSON error location is invalid.');
            }
            $diagnostics[] = ['file' => $error['file'] ?? '[unavailable]', 'line' => $error['line'] ?? null, 'message' => $error['message']];
        }

        return $diagnostics;
    }

    /**
     * Lists native changed files without interpreting diff text as line or AST identity.
     *
     * @param list<mixed> $changes Optional native file-diff records, validated without displaying source.
     * @param list<string> $files The native changed-file list corresponding to the total.
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function rectorChanges(array $changes, array $files): array
    {
        foreach ($changes as $change) {
            if (!is_array($change) || !is_string($change['file'] ?? null) || !in_array($change['file'], $files, true) || !is_string($change['diff'] ?? null)) {
                throw new InvalidArgumentException('Rector JSON change location is invalid.');
            }
        }
        $diagnostics = [];
        foreach ($files as $file) {
            $diagnostics[] = ['file' => $file, 'line' => null, 'message' => 'Semantic changes proposed; the native JSON does not provide a line field.'];
        }

        return $diagnostics;
    }
}
