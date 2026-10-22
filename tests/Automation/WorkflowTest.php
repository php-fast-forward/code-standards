<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;

/** Contract assertions complement actionlint and real Git/CLI regressions. */
final class WorkflowTest extends TestCase
{
    /** Verifies supply-chain pins for every external Action in executable repository workflows. */
    public function testExternalActionsUseReviewedImmutableCommits(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml') as $file) {
            preg_match_all('~(?:^|\n)\s*(?:-\s*)?uses:\s*([^\s#]+)~', file_get_contents($file), $matches);
            foreach ($matches[1] as $reference) {
                if (!str_starts_with($reference, './')) {
                    self::assertMatchesRegularExpression('/@[a-f0-9]{40}$/', $reference, $file);
                }
            }
        }
    }

    /** Keeps the checking adapter bound to unprivileged PR events and disposable hosted runners. */
    public function testPrWorkflowHasNoPrivilegedEventSecretOrSelfHostedRunner(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/code-standards-check.yml');
        self::assertStringContainsString('runs-on: ubuntu-latest', $workflow);
        self::assertStringContainsString('[[ "$STANDARDS_EVENT" == pull_request ]] || exit 1', $workflow);
        self::assertStringContainsString('github.event.pull_request.head.sha', $workflow);
        self::assertStringContainsString('github.event.pull_request.base.sha', $workflow);
        self::assertSame(2, substr_count($workflow, 'persist-credentials: false'));
        self::assertStringContainsString('--no-dev --no-plugins --no-scripts', $workflow);
        self::assertStringNotContainsString('secrets.', $workflow);
        self::assertStringNotContainsString('contents: write', $workflow);
        self::assertStringNotContainsString('self-hosted', $workflow);
    }
}
