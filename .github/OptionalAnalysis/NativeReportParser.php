<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use DOMDocument;
use DOMElement;
use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Refactoring\RectorJsonReportParser;
use InvalidArgumentException;

/** Classifies qualified native schemas together with their exits; human output is never scraped. */
final class NativeReportParser
{
    /** Reuses native Rector validation while keeping workflow classification and presentation here. */
    public function __construct(private readonly RectorJsonReportParser $rectorReports) {}

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
        $report = $this->rectorReports->parse($process);
        $changes = $report['totals']['changed_files'];
        $errorCount = $report['totals']['errors'];
        $diagnostics = [...$this->rectorErrors($report['errors'] ?? []), ...$this->rectorChanges($report['changed_files'] ?? [])];
        if (0 !== $errorCount) {
            return new AnalysisResult('Rector', 'operational-error', findingCount: $changes, diagnostics: $diagnostics, details: $errorCount . " native processing error(s).\n" . $process->stderr, nativeExit: $process->exitCode);
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

    /** @return list<DOMElement> Expected structural elements only; whitespace text/comments are allowed, snippets remain opaque. */
    private function children(DOMElement $parent, string $name): array
    {
        $elements = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($name !== $child->tagName) {
                    throw new InvalidArgumentException('Unexpected PHPMND XML structure.');
                }
                $elements[] = $child;

                continue;
            }
            if (
                XML_COMMENT_NODE === $child->nodeType
                || (XML_TEXT_NODE === $child->nodeType
                    && '' === trim($child->textContent))
            ) {
                continue;
            }

            throw new InvalidArgumentException('Unexpected PHPMND XML structural node.');
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
            $diagnostics[] = ['file' => $error['file'] ?? '[unavailable]', 'line' => $error['line'] ?? null, 'message' => $error['message']];
        }

        return $diagnostics;
    }

    /**
     * Lists validated native paths without inferring diff lines or AST identity.
     *
     * @param list<string> $files Native changed paths corresponding to the validated total.
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function rectorChanges(array $files): array
    {
        $diagnostics = [];
        foreach ($files as $file) {
            $diagnostics[] = ['file' => $file, 'line' => null, 'message' => 'Semantic changes proposed; the native JSON does not provide a line field.'];
        }

        return $diagnostics;
    }
}
