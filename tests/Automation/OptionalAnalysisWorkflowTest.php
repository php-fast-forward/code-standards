<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Executes the real final policy/fallback scripts with synthetic runner files, never actual analyzers. */
final class OptionalAnalysisWorkflowTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string, int}> The final gate requires a complete explicit decision. */
    public static function decisions(): iterable
    {
        yield 'both disabled' => ['false', 'false', '', '', 0];
        yield 'classified advisory' => ['true', 'false', 'true', 'false', 0];
        yield 'classified blocking' => ['true', 'false', 'true', 'true', 1];
        yield 'Rector operational error' => ['false', 'true', 'true', 'true', 1];
        yield 'missing result' => ['true', 'false', '', '', 1];
        yield 'missing policy' => ['true', 'false', 'true', '', 1];
        yield 'incomplete summary' => ['true', 'true', 'false', 'false', 1];
        yield 'invalid blocking value' => ['true', 'false', 'true', '0', 1];
    }

    /** Failed/missing classifications cannot pass because a preceding step was skipped or tolerated. */
    #[DataProvider('decisions')]
    public function testMandatoryFinalGate(string $magic, string $rector, string $summary, string $blocking, int $expected): void
    {
        $script = $this->script('Enforce explicit optional-analysis result');
        $process = new Process(['bash', '-euo', 'pipefail', '-c', $script], env: [
            'STANDARDS_MAGIC_ENABLED' => $magic, 'STANDARDS_RECTOR_ENABLED' => $rector,
            'STANDARDS_SUMMARY_WRITTEN' => $summary, 'STANDARDS_BLOCKING' => $blocking,
        ], timeout: 5);
        self::assertSame($expected, $process->run(), $process->getErrorOutput());
    }

    /** Fallback summarizes missing native results as errors and preserves disabled tools as disabled. */
    public function testFallbackSummaryIsAlwaysAvailableWithoutPhpOrToolCheckout(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'optional-summary-');
        try {
            $process = new Process(['bash', '-euo', 'pipefail', '-c', $this->script('Always summarize unavailable or disabled analyses')], env: [
                'STANDARDS_MAGIC_ENABLED' => 'true', 'STANDARDS_RECTOR_ENABLED' => 'false', 'GITHUB_STEP_SUMMARY' => $file,
            ], timeout: 5);
            self::assertSame(0, $process->run(), $process->getErrorOutput());
            $summary = file_get_contents($file);
            self::assertStringContainsString('| Magic Numbers | operational-error | unavailable |', $summary);
            self::assertStringContainsString('| Rector | disabled | not executed |', $summary);
            self::assertStringNotContainsString('| clean |', $summary);
        } finally {
            unlink($file);
        }
    }

    /** Default callers neither enable an optional engine nor install a second graph for the disabled analysis job. */
    public function testOptionalWiringRetainsDefaultsAndHasNoGenericFailureOverride(): void
    {
        $root = dirname(__DIR__, 2);
        $public = file_get_contents($root . '/.github/workflows/code-standards.yml');
        $workflow = file_get_contents($root . '/.github/workflows/code-standards-analysis.yml');
        self::assertStringContainsString("magic-numbers:\n        description:", $public);
        self::assertStringContainsString("rector:\n        description:", $public);
        foreach (['magic-numbers', 'rector'] as $engine) {
            self::assertMatchesRegularExpression('~      ' . $engine . ':\n        description: [^\n]+\n        default: false\n        type: boolean~', $public);
        }
        self::assertStringContainsString('if: inputs.magic-numbers || inputs.rector', $workflow);
        self::assertStringContainsString("if: always() && steps.analysis.outputs.summary-written != 'true'", $workflow);
        self::assertStringContainsString("- name: Enforce explicit optional-analysis result\n        if: always()", $workflow);
        self::assertStringNotContainsString('continue-on-error', $workflow);
        self::assertStringNotContainsString('upload-artifact', $workflow);
        self::assertStringNotContainsString('--apply', $workflow);
    }

    /** The basic released-channel wrapper stays compatible while documented optional inputs match the source API. */
    public function testBasicConsumerWrapperAndOptionalApiFragmentHaveDistinctContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $wrapper = file_get_contents($root . '/resources/workflows/code-standards.yml');
        self::assertStringContainsString('uses: php-fast-forward/code-standards/.github/workflows/code-standards.yml@v0', $wrapper);
        preg_match_all('/^      ([a-z][a-z-]*):/m', $wrapper, $basic);
        self::assertSame(['php-version'], $basic[1]);
        self::assertSame(0, preg_match('/^\s*#\s*(?:magic-numbers|rector)(?:-policy|-config)?:/m', $wrapper));

        $public = file_get_contents($root . '/.github/workflows/code-standards.yml');
        $documentation = file_get_contents($root . '/docs/optional-analysis.rst');
        preg_match_all('/^      ([a-z][a-z-]*):$/m', $public, $declared);
        preg_match_all('/^     ([a-z][a-z-]*):/m', $documentation, $documented);
        self::assertNotEmpty($documented[1]);
        foreach ($documented[1] as $input) {
            self::assertContains($input, $declared[1]);
        }
    }

    /** Extracts the actual named YAML run block; no duplicate implementation replaces the tested gate. */
    private function script(string $name): string
    {
        $lines = file(dirname(__DIR__, 2) . '/.github/workflows/code-standards-analysis.yml', FILE_IGNORE_NEW_LINES);
        $found = false;
        $collect = false;
        $script = [];
        foreach ($lines as $line) {
            if (str_contains($line, '- name: ' . $name)) {
                $found = true;
            }
            if ($found && '        run: |' === $line) {
                $collect = true;
                continue;
            }
            if ($collect) {
                if (!str_starts_with($line, '          ')) {
                    break;
                }
                $script[] = substr($line, 10);
            }
        }
        self::assertNotEmpty($script, $name);

        return implode("\n", $script);
    }
}
