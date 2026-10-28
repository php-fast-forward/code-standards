<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use InvalidArgumentException;

/** Records a classified optional analysis without equating skipped work or process errors with a clean run. */
final readonly class AnalysisResult
{
    /**
     * Retains native totals separately from the bounded diagnostic presentation.
     *
     * @param string $tool A controlled tool label, never a name taken from child output.
     * @param string $status Disabled, not-executed, clean, findings or operational-error.
     * @param int|null $selectedCount Known PHP selection count; null when native configuration owns the scope.
     * @param int|null $findingCount PHPMND findings or Rector changed files; null means unavailable, not zero.
     * @param list<array{file: string, line: int|null, message: string}> $diagnostics Native locations/messages.
     * @param string $details Bounded later by the renderer, and never interpreted as Markdown or workflow commands.
     * @param int|null $nativeExit Captured Code Standards invocation status; null if completion is unavailable.
     */
    public function __construct(
        public string $tool,
        public string $status,
        public ?int $selectedCount = null,
        public ?int $findingCount = null,
        public array $diagnostics = [],
        public string $details = '',
        public ?int $nativeExit = null,
    ) {
        if (
            !in_array($tool, ['Magic Numbers', 'Rector'], true)
            || !in_array($status, ['disabled', 'not-executed', 'clean', 'findings', 'operational-error'], true)
            || (null !== $selectedCount
                && $selectedCount < 0)
            || (null !== $findingCount
                && $findingCount < 0)
            || (null !== $nativeExit
                && $nativeExit < 0)
        ) {
            throw new InvalidArgumentException('Invalid optional-analysis result.');
        }
    }

    /** Only verified findings may become advisory; errors stay failing under either policy. */
    public function blocks(string $policy): bool
    {
        if (!in_array($policy, ['report', 'fail'], true)) {
            throw new InvalidArgumentException('Findings policy must be report or fail.');
        }

        return 'operational-error' === $this->status || ('findings' === $this->status && 'fail' === $policy);
    }
}
