<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

/** Renders only bounded, escaped diagnostics; no native output becomes Markdown or workflow commands. */
final class SummaryRenderer
{
    private const MAX_BYTES = 65536;
    private const MAX_DIAGNOSTICS = 100;
    private const MAX_FIELD_BYTES = 2048;

    /** @param list<AnalysisResult> $results Known tools/results, independent of findings severity. */
    public function render(array $results): string
    {
        $summary = "## Optional Code Standards analyses\n\n";
        $summary .= "| Tool | Status | Selected PHP files | Native count | Invocation exit |\n| --- | --- | ---: | ---: | ---: |\n";
        foreach ($results as $result) {
            $label = 'Rector' === $result->tool ? 'changed files' : 'findings';
            $summary .= '| ' . $result->tool . ' | ' . $result->status . ' | ' . ($result->selectedCount ?? 'unknown/native scope') . ' | ' . ($result->findingCount ?? 'unavailable') . ' ' . $label . ' | ' . ($result->nativeExit ?? 'not available') . " |\n";
        }
        $summary .= "\n";
        foreach ($results as $result) {
            $summary .= '### ' . $result->tool . "\n\n";
            $summary .= "<pre>\n";
            $lines = '' === $result->details ? [] : [$result->details];
            foreach (array_slice($result->diagnostics, 0, self::MAX_DIAGNOSTICS) as $diagnostic) {
                $lines[] = $diagnostic['file'] . ':' . ($diagnostic['line'] ?? '?') . ' - ' . $diagnostic['message'];
            }
            foreach ($lines as $line) {
                $escaped = $this->escape($line) . "\n";
                if (strlen($summary) + strlen($escaped) > self::MAX_BYTES - 512) {
                    return $summary . "</pre>\n\nAdditional diagnostics omitted to keep the summary bounded. Native totals above are unchanged.\n";
                }
                $summary .= $escaped;
            }
            $summary .= "</pre>\n\n";
            if (count($result->diagnostics) > self::MAX_DIAGNOSTICS) {
                $summary .= "Additional diagnostics omitted; native totals are not truncated.\n\n";
            }
        }

        return $summary;
    }

    /** Control bytes remain visible and escaped; untrusted closing tags cannot end the preformatted block. */
    private function escape(string $text): string
    {
        $truncated = strlen($text) > self::MAX_FIELD_BYTES;
        $text = substr($text, 0, self::MAX_FIELD_BYTES);
        $quoted = json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        $visible = substr($quoted, 1, -1) . ($truncated ? '… [truncated]' : '');

        return htmlspecialchars($visible, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
