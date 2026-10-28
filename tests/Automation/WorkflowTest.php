<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;

/** Contract assertions complement actionlint and real Git/CLI regressions. */
final class WorkflowTest extends TestCase
{
    /** Gives the consumer reusable only a native PR trigger while retaining manual project tests. */
    public function testConsumerCallerDeclaresOnlyPullRequestsWithoutRemovingQualityEvents(): void
    {
        $directory = dirname(__DIR__, 2) . '/.github/workflows';
        $callers = [];
        foreach ([...glob($directory . '/*.yml'), ...glob($directory . '/*.yaml')] as $file) {
            if (preg_match('~^\s+uses:\s+["\']?\./\.github/workflows/code-standards\.yml(?:["\']|\s|$)~m', file_get_contents($file))) {
                $callers[] = basename($file);
            }
        }
        self::assertSame(['repository-pr-standards.yml'], $callers);

        $consumer = file_get_contents($directory . '/repository-pr-standards.yml');
        self::assertMatchesRegularExpression('~^on:\n  pull_request:\npermissions:\n  contents: read\njobs:~m', $consumer);
        self::assertStringContainsString("  reusable-check:\n    uses: ./.github/workflows/code-standards.yml", $consumer);
        self::assertStringContainsString("      magic-numbers: true\n      magic-numbers-policy: report", $consumer);

        $quality = file_get_contents($directory . '/repository-ci.yml');
        self::assertMatchesRegularExpression('~^on:\n  pull_request:\n  push:\n    branches: \[main\]\n  workflow_dispatch:\npermissions:~m', $quality);
        self::assertStringContainsString("  tests:\n    runs-on: ubuntu-latest", $quality);
        self::assertStringContainsString("php: ['8.3', '8.4', '8.5']", $quality);
        self::assertStringContainsString('run: composer check', $quality);
    }

    /** Keeps external Actions on verified published channels or reviewed release commits. */
    public function testExternalActionsUsePublishedMajorChannels(): void
    {
        $references = [
            'Actions-R-Us/actions-tagger@330ddfac760021349fef7ff62b372f2f691c20fb',
            'actions/checkout@v7',
            'shivammathur/setup-php@v2',
            'php-fast-forward/changelog/.github/workflows/changelog.yml@v0',
            'php-fast-forward/code-standards/.github/workflows/code-standards.yml@v0',
        ];
        $files = [
            ...glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml'),
            ...glob(dirname(__DIR__, 2) . '/resources/workflows/*.yml'),
        ];
        foreach ($files as $file) {
            preg_match_all('~(?:^|\n)\s*(?:-\s*)?uses:\s*([^\s#]+)~', file_get_contents($file), $matches);
            foreach ($matches[1] as $reference) {
                if (!str_starts_with($reference, './')) {
                    self::assertContains($reference, $references, $file);
                }
            }
        }
    }

    /** Reserves promotion for genuine published stable release events with one serialized writer. */
    public function testReleaseChannelsUseThePinnedActionOnPublishedReleases(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root . '/.github/workflows/repository-release-channels.yml');
        self::assertStringContainsString("  release:\n    types: [published]", $workflow);
        self::assertStringNotContainsString('workflow_call:', $workflow);
        self::assertStringNotContainsString('workflow_dispatch:', $workflow);
        self::assertStringContainsString("github.repository == 'php-fast-forward/code-standards'", $workflow);
        self::assertStringContainsString('github.event.release.draft == false && github.event.release.prerelease == false', $workflow);
        self::assertStringContainsString('group: release-channels-${{ github.repository }}', $workflow);
        self::assertStringContainsString('cancel-in-progress: false', $workflow);
        self::assertStringContainsString("prefer_branch_releases: 'false'", $workflow);
        self::assertStringContainsString("publish_latest_tag: 'true'", $workflow);
        self::assertStringContainsString('contents: write', $workflow);
        self::assertStringNotContainsString('checkout@', $workflow);
        self::assertStringNotContainsString('  release-channels:', file_get_contents($root . '/.github/workflows/repository-changelog.yml'));
    }

    /** Keeps the checking adapter bound to unprivileged PR events and disposable hosted runners. */
    public function testPrWorkflowHasNoPrivilegedEventSecretOrSelfHostedRunner(): void
    {
        foreach (['code-standards-check.yml', 'code-standards-analysis.yml'] as $file) {
            $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/' . $file);
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
}
