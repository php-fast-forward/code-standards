<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Standards;

/** Describes one check, including incomplete work and operational failures. */
final readonly class CheckResult
{
    /**
     * @param string $status Clean, findings, syntax-failed or operational-error.
     * @param bool $complete Whether every syntax check and selected ECS batch completed consistently.
     * @param int $styleSubmitted Files submitted to completed native batches; ECS may skip unchanged or excluded files.
     * @param array<string,mixed> $files Validated native file diagnostics and diffs.
     * @param list<array<string,mixed>> $errors Operation diagnostics, separate from style findings.
     */
    public function __construct(
        public string $status,
        public int $exitCode,
        public bool $complete,
        public int $selected,
        public int $syntaxChecked,
        public int $styleSubmitted,
        public array $files = [],
        public array $errors = [],
        public string $stderr = '',
    ) {}

    /** @return array<string,mixed> Versioned, single-document presentation without timing or human banners. */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'command' => 'check',
            'status' => $this->status,
            'exit_code' => $this->exitCode,
            'complete' => $this->complete,
            'selection' => [
                'files' => $this->selected,
                'syntax_checked' => $this->syntaxChecked,
                'syntax_remaining' => $this->selected - $this->syntaxChecked,
                'style_submitted' => $this->styleSubmitted,
                'style_remaining' => $this->selected - $this->styleSubmitted,
            ],
            'files' => (object) $this->files,
            'errors' => $this->errors,
            'diagnostics' => ['stderr' => $this->stderr],
        ];
    }
}
