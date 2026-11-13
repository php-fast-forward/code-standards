<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

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
        self::assertMatchesRegularExpression('~^on:\n  pull_request:\n  workflow_dispatch:\n  workflow_call:\n~m', $quality);
        self::assertStringContainsString('value: ${{ jobs.compiled.outputs.artifact }}', $quality);
        self::assertStringContainsString("  tests:\n    runs-on: ubuntu-latest", $quality);
        self::assertStringContainsString("php: ['8.3', '8.4', '8.5']", $quality);
        self::assertStringContainsString('run: composer check', $quality);
        self::assertSame(1, substr_count($quality, 'box compile'));
        self::assertStringContainsString('needs: build', $quality);
        self::assertStringContainsString('needs: [build, tests, runtime]', $quality);
    }

    /** Keeps approved external Actions on stable version labels without duplicating their current versions. */
    public function testExternalActionsUseAllowedProvidersAndStableVersionLabels(): void
    {
        $actions = [
            'Actions-R-Us/actions-tagger',
            'actions/create-github-app-token',
            'actions/github-script',
            'actions/checkout',
            'actions/upload-artifact',
            'actions/download-artifact',
            'stefanzweifel/git-auto-commit-action',
            'shivammathur/setup-php',
            'php-fast-forward/changelog/.github/workflows/changelog.yml',
            'php-fast-forward/changelog/.github/workflows/changelog-pr-status.yml',
            'php-fast-forward/changelog/.github/actions/version',
            'php-fast-forward/code-standards/.github/workflows/code-standards.yml',
        ];
        $files = [
            ...glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml'),
            ...glob(dirname(__DIR__, 2) . '/resources/workflows/*.yml'),
        ];
        foreach ($files as $file) {
            preg_match_all('~(?:^|\n)\s*(?:-\s*)?uses:\s*([^\s#]+)~', file_get_contents($file), $matches);
            foreach ($matches[1] as $reference) {
                if (!str_starts_with($reference, './')) {
                    self::assertSame(1, preg_match('~\A([^@]+)@v(?:0|[1-9][0-9]*)(?:\.(?:0|[1-9][0-9]*)){0,2}\z~', $reference, $parts), $file . ': ' . $reference);
                    self::assertContains($parts[1], $actions, $file);
                }
            }
        }
    }

    /** A candidate's self-reported manifest must not authorize its first execution. */
    public function testCommittedPharComparisonPrecedesRuntimeQualification(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-ci.yml');
        $comparison = strpos($workflow, 'cmp "$RUNNER_TEMP/code-standards" dist/code-standards');
        $execution = strpos($workflow, 'STANDARDS_PHAR="$RUNNER_TEMP/phar/code-standards"');
        self::assertNotFalse($comparison);
        self::assertNotFalse($execution);
        self::assertLessThan($execution, $comparison);
        self::assertStringNotContainsString('STANDARDS_PHAR="$GITHUB_WORKSPACE/dist/code-standards"', $workflow);
    }

    /** Runs the actual parity step against owned fixtures: differences authorize refresh, I/O errors do not. */
    public function testBinaryComparisonNeverExecutesCandidateBytesOrMasksReadErrors(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-ci.yml');
        self::assertSame(1, preg_match('~id: parity\n        shell: bash\n        run: \|\n(.*?)(?=      - )~s', $workflow, $matches));
        $script = preg_replace('/^          /m', '', $matches[1]);
        $directory = sys_get_temp_dir() . '/standards-cmp-' . bin2hex(random_bytes(8));
        mkdir($directory . '/dist', 0700, true);
        try {
            file_put_contents($directory . '/code-standards', 'reviewed bytes');
            foreach (['reviewed bytes', '<?php file_put_contents("executed", "unreviewed");', null] as $candidate) {
                if (null === $candidate) {
                    unlink($directory . '/dist/code-standards');
                } else {
                    file_put_contents($directory . '/dist/code-standards', $candidate);
                }
                file_put_contents($directory . '/output', '');
                $process = new Process(['bash', '-c', $script], $directory, [
                    'RUNNER_TEMP' => $directory,
                    'GITHUB_OUTPUT' => $directory . '/output',
                ], timeout: 10);
                $process->run();
                self::assertSame(null === $candidate ? 2 : 0, $process->getExitCode(), $process->getErrorOutput());
                self::assertSame(null === $candidate ? '' : ('reviewed bytes' === $candidate ? "stale=false\n" : "stale=true\n"), file_get_contents($directory . '/output'));
                self::assertFileDoesNotExist($directory . '/executed');
            }
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /** Keeps build execution separate from the narrowly scoped marketplace commit job. */
    public function testCompiledRefreshNeverExecutesPullRequestCodeWithTheWriter(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-ci.yml');
        self::assertStringNotContainsString('secrets.', $workflow);
        $refresh = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-phar-refresh.yml');
        self::assertStringContainsString('github.event.workflow_run.head_repository.full_name == github.repository', $refresh);
        self::assertStringContainsString('ref: ${{ steps.pull-request.outputs.branch }}', $refresh);
        self::assertStringContainsString('select(.state == "open"', $refresh);
        self::assertStringContainsString('test "$(git rev-parse HEAD)" = "$SOURCE_SHA"', $refresh);
        self::assertStringContainsString('run-id: ${{ github.event.workflow_run.id }}', $refresh);
        self::assertStringContainsString('file_pattern: dist/code-standards', $refresh);
        self::assertStringContainsString('uses: stefanzweifel/git-auto-commit-action@', $refresh);
        self::assertStringNotContainsString('php ', $refresh);
        self::assertStringNotContainsString('permission-workflows:', $refresh);
        self::assertStringNotContainsString('--force', $refresh);
    }

    /** Keeps preparation behind the source/compiled check instead of publishing a stale base. */
    public function testVersionPreparationRequiresSuccessfulBaseQuality(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-changelog.yml');
        self::assertStringContainsString('uses: ./.github/workflows/repository-ci.yml', $workflow);
        self::assertStringContainsString("  version:\n    needs: quality\n", $workflow);
        self::assertStringContainsString('base-ref: ${{ github.sha }}', $workflow);
        self::assertStringContainsString('artifact-ids: ${{ needs.quality.outputs.artifact }}', $workflow);
        self::assertStringContainsString('uses: php-fast-forward/changelog/.github/actions/version@v0', $workflow);
        self::assertSame(3, substr_count($workflow, "generated-files: '[\"dist/code-standards\"]'"));
        self::assertStringContainsString('head-sha: ${{ needs.version.outputs.head-sha }}', $workflow);
        self::assertStringContainsString('base-sha: ${{ github.sha }}', $workflow);
        self::assertStringContainsString('uses: php-fast-forward/changelog/.github/workflows/changelog-pr-status.yml@v0', $workflow);
        self::assertStringContainsString('prepare-release: false', $workflow);
        self::assertStringContainsString('group: changelog-${{ github.repository }}-main', $workflow);
        self::assertSame(5, substr_count($workflow, "if: steps.base.outputs.current == 'true'"));
        self::assertStringContainsString('resolve-pull-requests: true', $workflow);
        self::assertStringNotContainsString('box compile', $workflow);
    }

    /** A failed-job rerun reuses the successful producer's artifact instead of inventing another attempt. */
    public function testArtifactConsumersUseTheSuccessfulBuildIdentity(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-ci.yml');
        self::assertStringContainsString('artifact: ${{ steps.candidate.outputs.artifact-id }}', $workflow);
        self::assertSame(2, substr_count($workflow, 'artifact-ids: ${{ needs.build.outputs.artifact }}'));
        self::assertSame(1, substr_count($workflow, 'name: code-standards-build-'));
    }

    /** Exercises the actual classifier with Git changes, including dependency and binary updates. */
    public function testRuntimeInputsRebuildButDocumentationAndOrdinaryTestsDoNot(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-ci.yml');
        self::assertSame(1, preg_match('~      - id: inputs\n.*?        run: \|\n(.*?)  build:\n~s', $workflow, $matches));
        $script = preg_replace('/^          /m', '', $matches[1]);
        $directory = sys_get_temp_dir() . '/standards-inputs-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $environment = [
            'GIT_DIR' => false,
            'GIT_WORK_TREE' => false,
            'GIT_INDEX_FILE' => false,
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GITHUB_OUTPUT' => $directory . '/output',
            'RUNNER_TEMP' => $directory,
        ];
        $git = static function (array $arguments) use ($directory, $environment): string {
            $process = new Process(['git', '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', ...$arguments], $directory, $environment);
            $process->mustRun();

            return trim($process->getOutput());
        };
        try {
            $git(['init', '--quiet']);
            $git(['commit', '--quiet', '--allow-empty', '-m', 'base']);
            foreach (['docs/example.rst' => false, 'tests/UnitTest.php' => false, '.changelog/example.md' => false, 'composer.lock' => true, 'resources/template.txt' => true, 'dist/code-standards' => true, 'tests/Distribution/ArchiveTest.php' => true, 'unknown-input' => true] as $path => $expected) {
                $base = $git(['rev-parse', 'HEAD']);
                (new Filesystem())->dumpFile($directory . '/' . $path, 'fixture');
                $git(['add', '--', $path]);
                $git(['commit', '--quiet', '-m', 'change']);
                file_put_contents($directory . '/output', '');
                $process = new Process(['bash', '-e', '-o', 'pipefail', '-c', $script], $directory, $environment + ['BASE' => $base, 'HEAD' => $git(['rev-parse', 'HEAD']), 'EVENT' => 'pull_request']);
                $process->mustRun();
                self::assertSame('build=' . ($expected ? 'true' : 'false') . "\n", file_get_contents($directory . '/output'), $path);
            }
            $ancestor = $git(['rev-parse', 'HEAD']);
            $git(['checkout', '--quiet', '-b', 'main-advanced']);
            (new Filesystem())->dumpFile($directory . '/src/main-only.php', 'main change');
            $git(['add', 'src/main-only.php']);
            $git(['commit', '--quiet', '-m', 'main runtime update']);
            $base = $git(['rev-parse', 'HEAD']);
            $git(['checkout', '--quiet', '-b', 'docs-branch', $ancestor]);
            file_put_contents($directory . '/docs/example.rst', 'documentation update');
            $git(['add', 'docs/example.rst']);
            $git(['commit', '--quiet', '-m', 'documentation']);
            foreach (['pull_request' => false, 'push' => true] as $event => $expected) {
                file_put_contents($directory . '/output', '');
                $process = new Process(['bash', '-e', '-o', 'pipefail', '-c', $script], $directory, $environment + ['BASE' => $base, 'HEAD' => $git(['rev-parse', 'HEAD']), 'EVENT' => $event]);
                $process->mustRun();
                self::assertSame('build=' . ($expected ? 'true' : 'false') . "\n", file_get_contents($directory . '/output'), $event);
            }
            $base = $git(['rev-parse', 'HEAD']);
            $git(['mv', 'resources/template.txt', 'docs/retired.md']);
            $git(['commit', '--quiet', '-m', 'retire bundled resource']);
            file_put_contents($directory . '/output', '');
            $process = new Process(['bash', '-e', '-o', 'pipefail', '-c', $script], $directory, $environment + ['BASE' => $base, 'HEAD' => $git(['rev-parse', 'HEAD']), 'EVENT' => 'pull_request']);
            $process->mustRun();
            self::assertSame("build=true\n", file_get_contents($directory . '/output'), 'Moving a resource out of the runtime must rebuild.');
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /** Executes the real selector without network; protected and owned version branches are never App targets. */
    public function testRefreshSelectsOnlyOneOpenPullRequestAtTheQualifiedHead(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-phar-refresh.yml');
        self::assertSame(1, preg_match('~name: Locate the still-open source pull request\n.*?        run: \|\n(.*?)(?=      - )~s', $workflow, $matches));
        $script = <<<'BASH'
gh() {
    case "$2" in
        repos/fixture/standards/commits/*/pulls) printf '%s\n' "$MOCK_PRS" ;;
        repos/fixture/standards/branches/*) printf '%s\n' "$MOCK_PROTECTED" ;;
        *) return 71 ;;
    esac
}
BASH;
        $script .= "\n" . preg_replace('/^          /m', '', $matches[1]);
        $pullRequest = ['state' => 'open', 'base' => ['ref' => 'main'], 'head' => ['sha' => str_repeat('a', 40), 'ref' => 'dependabot/composer/runtime', 'repo' => ['full_name' => 'fixture/standards']]];
        $closed = $pullRequest;
        $closed['state'] = 'closed';
        $advanced = $pullRequest;
        $advanced['head']['sha'] = str_repeat('b', 40);
        $fork = $pullRequest;
        $fork['head']['repo']['full_name'] = 'fork/standards';
        $otherBase = $pullRequest;
        $otherBase['base']['ref'] = 'other-line';
        $main = $pullRequest;
        $main['head']['ref'] = 'main';
        $managed = $pullRequest;
        $managed['head']['ref'] = 'changelog/version-phar';
        $output = tempnam(sys_get_temp_dir(), 'standards-refresh-');
        try {
            foreach (['valid' => [$pullRequest], 'closed' => [$closed], 'advanced' => [$advanced], 'fork' => [$fork], 'other-base' => [$otherBase], 'main' => [$main], 'managed' => [$managed], 'protected' => [$pullRequest], 'missing' => [], 'ambiguous' => [$pullRequest, $pullRequest]] as $case => $fixture) {
                file_put_contents($output, '');
                $process = new Process(['bash', '-e', '-o', 'pipefail', '-c', $script], env: [
                    'SOURCE_SHA' => $pullRequest['head']['sha'],
                    'SOURCE_BRANCH' => $fixture[0]['head']['ref'] ?? $pullRequest['head']['ref'],
                    'GH_REPO' => $pullRequest['head']['repo']['full_name'],
                    'GITHUB_OUTPUT' => $output,
                    'MOCK_PRS' => json_encode($fixture, JSON_THROW_ON_ERROR),
                    'MOCK_PROTECTED' => 'protected' === $case ? 'true' : 'false',
                ]);
                self::assertSame('protected' === $case ? 1 : 0, $process->run(), $process->getErrorOutput());
                self::assertSame('valid' === $case ? 'branch=' . $pullRequest['head']['ref'] . "\n" : '', file_get_contents($output), $case);
            }
        } finally {
            unlink($output);
        }
    }

    /** Prevents silent GITHUB_TOKEN publication by keeping the App identity explicit and publication-only. */
    public function testChangelogCallerSelectsTheCompatiblePublicationOnlyApp(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/repository-changelog.yml');
        self::assertStringContainsString('changelog.yml@v0', $workflow);
        self::assertStringContainsString("publish-app-client-id: 'Iv23liHqRng02H747IYa'", $workflow);
        self::assertStringContainsString('publish-environment: release-channels', $workflow);
        self::assertStringContainsString('managed-branch: changelog/version-phar', $workflow);
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
            self::assertStringContainsString('--no-plugins --no-scripts', $workflow);
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
