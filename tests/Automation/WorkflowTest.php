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
    public function testExternalActionsUseVerifiedPublishedReferences(): void
    {
        $references = [
            'Actions-R-Us/actions-tagger@v2.0.3',
            'actions/create-github-app-token@v3.3.0',
            'actions/github-script@v8',
            'actions/checkout@v7',
            'shivammathur/setup-php@v2',
            'php-fast-forward/changelog/.github/workflows/changelog.yml@4fe3cb26a252b7c41758c50ffcfe5391bf93a60a',
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

    /** Prevents silent GITHUB_TOKEN publication by keeping the App identity explicit and publication-only. */
    public function testChangelogCallerSelectsTheCompatiblePublicationOnlyApp(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-changelog.yml');
        self::assertStringContainsString('changelog.yml@4fe3cb26a252b7c41758c50ffcfe5391bf93a60a', $workflow);
        self::assertStringContainsString("publish-app-client-id: 'Iv23liHqRng02H747IYa'", $workflow);
        self::assertStringContainsString('publish-environment: release-channels', $workflow);
        self::assertStringContainsString('managed-branch: changelog/version-recovery-98', $workflow);
        self::assertStringContainsString('publish-app-private-key: ${{ secrets.RELEASE_CHANNEL_APP_PRIVATE_KEY }}', $workflow);
        self::assertDoesNotMatchRegularExpression('/^\s+app-client-id:/m', $workflow);
        self::assertDoesNotMatchRegularExpression('/^\s+app-private-key:/m', $workflow);
        self::assertStringNotContainsString('secrets: inherit', $workflow);
        self::assertStringNotContainsString('automation-actor:', $workflow);
    }

    /** Uses one deployable environment secret identifier rather than a logical workflow-only input name. */
    public function testReleaseAppEnvironmentSecretNameIsValidAndShared(): void
    {
        $root = dirname(__DIR__, 2) . '/.github/workflows/';
        foreach (['repository-changelog.yml', 'repository-release-channels.yml'] as $file) {
            $workflow = file_get_contents($root . $file);
            self::assertStringContainsString('secrets.RELEASE_CHANNEL_APP_PRIVATE_KEY', $workflow);
            self::assertStringNotContainsString('secrets.app-private-key', $workflow);
        }
        self::assertMatchesRegularExpression('/^[A-Z_][A-Z0-9_]*$/', 'RELEASE_CHANNEL_APP_PRIVATE_KEY');
    }

    /** Reserves promotion for genuine published/edited stable release events with one serialized writer. */
    public function testReleaseChannelsUseTheFullVersionActionOnPublishedAndEditedReleases(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root . '/.github/workflows/repository-release-channels.yml');
        self::assertStringContainsString("  release:\n    types: [published, edited]", $workflow);
        self::assertStringNotContainsString('workflow_call:', $workflow);
        self::assertStringNotContainsString('workflow_dispatch:', $workflow);
        self::assertStringContainsString("github.repository == 'php-fast-forward/code-standards'", $workflow);
        self::assertStringContainsString('github.event.release.draft == false && github.event.release.prerelease == false', $workflow);
        self::assertStringContainsString('group: release-channels-${{ github.repository }}', $workflow);
        self::assertStringContainsString('cancel-in-progress: false', $workflow);
        self::assertStringContainsString("prefer_branch_releases: 'true'", $workflow);
        self::assertStringContainsString('publish_latest_tag: ${{ steps.release.outputs.latest }}', $workflow);
        self::assertSame(2, substr_count($workflow, "if: steps.release.outputs.promote == 'true'"));
        self::assertStringContainsString('contents: read', $workflow);
        self::assertStringContainsString('environment: release-channels', $workflow);
        self::assertStringContainsString('repositories: code-standards', $workflow);
        self::assertStringContainsString('token: ${{ steps.channel-app.outputs.token }}', $workflow);
        self::assertStringContainsString('private-key: ${{ secrets.RELEASE_CHANNEL_APP_PRIVATE_KEY }}', $workflow);
        self::assertStringContainsString('permission-contents: write', $workflow);
        self::assertStringContainsString('permission-workflows: write', $workflow);
        self::assertStringContainsString("skip-token-revoke: 'false'", $workflow);
        self::assertStringNotContainsString('checkout@', $workflow);
        self::assertStringNotContainsString('  release-channels:', file_get_contents($root . '/.github/workflows/repository-changelog.yml'));
    }

    /** Keeps forks hosted and permits explicit custom runners only for trusted same-repository PRs. */
    public function testPrWorkflowHasNoPrivilegedEventSecretOrUntrustedCustomRunner(): void
    {
        $public = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/code-standards.yml');
        self::assertSame(2, substr_count($public, 'runs-on: ${{ inputs.runs-on }}'));
        self::assertStringContainsString("default: '[\"ubuntu-latest\"]'", $public);
        foreach (['code-standards-check.yml', 'code-standards-analysis.yml'] as $file) {
            $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/' . $file);
            self::assertStringContainsString("default: '[\"ubuntu-latest\"]'", $workflow);
            self::assertStringContainsString("github.event_name == 'pull_request' && github.event.pull_request.head.repo.full_name == github.repository &&", $workflow);
            self::assertStringContainsString('[[ "$STANDARDS_EVENT" == pull_request ]] || exit 1', $workflow);
            self::assertStringContainsString('github.event.pull_request.head.sha', $workflow);
            self::assertStringContainsString('github.event.pull_request.base.sha', $workflow);
            self::assertSame(2, substr_count($workflow, 'persist-credentials: false'));
            self::assertStringContainsString('--no-dev --no-plugins --no-scripts', $workflow);
            self::assertStringNotContainsString('secrets.', $workflow);
            self::assertStringNotContainsString('contents: write', $workflow);
            self::assertStringContainsString('[[ -n "$STANDARDS_REPOSITORY" && "$STANDARDS_HEAD_REPOSITORY" == "$STANDARDS_REPOSITORY" ]] || exit 1', $workflow);
            self::assertStringContainsString("startsWith(toJSON(fromJSON(inputs.runs-on)), '[')", $workflow);
            self::assertStringContainsString("contains(fromJSON(inputs.runs-on), 'self-hosted')", $workflow);
            self::assertStringContainsString("contains(fromJSON(inputs.runs-on), 'Linux')", $workflow);
            self::assertStringContainsString('[[ "$STANDARDS_RUNNER_OS" == Linux ]] || exit 1', $workflow);
            self::assertStringContainsString("id: runtime\n        shell: bash", $workflow);
            self::assertStringContainsString(
                'code-standards-check.yml' === $file ? "inputs.config == ''" : '!inputs.rector',
                $workflow,
            );
        }
    }
}
