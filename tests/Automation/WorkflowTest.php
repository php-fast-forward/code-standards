<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;

/** Contract assertions complement actionlint and real Git/CLI regressions. */
final class WorkflowTest extends TestCase
{
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
