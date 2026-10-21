<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Executes the actual workflow guards without running checkout or remote operations. */
final class WorkflowContextTest extends TestCase
{
    /** Rejects privileged checking events and binds source provenance to the called job identity. */
    public function testCheckGuardUsesExecutingWorkflowIdentity(): void
    {
        $environment = [
            'STANDARDS_EVENT' => 'pull_request',
            'STANDARDS_REPOSITORY' => 'php-fast-forward/code-standards',
            'STANDARDS_RUNTIME' => str_repeat('1', 40),
            'STANDARDS_BASE' => str_repeat('2', 40),
            'STANDARDS_HEAD' => str_repeat('3', 40),
        ];
        $script = $this->guard('code-standards-check.yml', 'Reject privileged events and invalid refs');
        self::assertSame(0, $this->guardExitCode($script, $environment));
        foreach (['STANDARDS_EVENT' => 'pull_request_target', 'STANDARDS_REPOSITORY' => 'foreign/tool',
            'STANDARDS_RUNTIME' => 'v0', 'STANDARDS_BASE' => 'main', 'STANDARDS_HEAD' => 'v0'] as $key => $value) {
            self::assertNotSame(0, $this->guardExitCode($script, [...$environment, $key => $value]));
        }
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/code-standards-check.yml');
        self::assertStringContainsString('fromJSON(toJSON(job)).workflow_sha', $workflow);
        self::assertStringNotContainsString('inputs.runtime-ref', $workflow);
        self::assertStringNotContainsString('github.workflow_sha', $workflow);
        self::assertStringContainsString('ref: ${{ fromJSON(toJSON(job)).workflow_sha }}', $workflow);
    }

    /** Allows only verified managed merges or deliberate recovery from canonical main. */
    public function testChannelGuardRefusesForeignUnmergedAndMismatchedEvents(): void
    {
        $environment = [
            'FF_REPOSITORY' => 'php-fast-forward/code-standards',
            'FF_RUNTIME_REPOSITORY' => 'php-fast-forward/code-standards',
            'FF_RUNTIME_SHA' => str_repeat('1', 40),
            'FF_EVENT' => 'pull_request_target', 'FF_REF' => 'refs/heads/main',
            'FF_ACTION' => 'closed', 'FF_MERGED' => 'true',
            'FF_HEAD_REPOSITORY' => 'php-fast-forward/code-standards',
            'FF_HEAD_BRANCH' => 'changelog/version', 'FF_BASE_BRANCH' => 'main',
            'FF_MERGE_SHA' => str_repeat('2', 40), 'FF_EXPECTED_SHA' => str_repeat('2', 40),
        ];
        $script = $this->guard('repository-release-channels.yml', 'Require a trusted repository workflow');
        self::assertSame(0, $this->guardExitCode($script, $environment));
        foreach (['FF_REPOSITORY' => 'foreign/repository', 'FF_RUNTIME_REPOSITORY' => 'foreign/tool',
            'FF_RUNTIME_SHA' => 'v0', 'FF_EVENT' => 'pull_request', 'FF_ACTION' => 'opened',
            'FF_MERGED' => 'false', 'FF_HEAD_REPOSITORY' => 'fork/tool',
            'FF_HEAD_BRANCH' => 'feature', 'FF_BASE_BRANCH' => 'other', 'FF_EXPECTED_SHA' => str_repeat('3', 40)] as $key => $value) {
            self::assertNotSame(0, $this->guardExitCode($script, [...$environment, $key => $value]), $key);
        }
        self::assertSame(0, $this->guardExitCode($script, [...$environment, 'FF_EVENT' => 'workflow_dispatch']));
        self::assertNotSame(0, $this->guardExitCode($script, [...$environment, 'FF_EVENT' => 'workflow_dispatch', 'FF_REF' => 'refs/heads/v0']));
    }

    /** Extracts the named step's real run block, failing if its structural contract changes. */
    private function guard(string $file, string $name): string
    {
        $lines = file(dirname(__DIR__, 2) . '/.github/workflows/' . $file, FILE_IGNORE_NEW_LINES);
        $found = false;
        $collecting = false;
        $script = [];
        foreach ($lines as $line) {
            if (str_contains($line, '- name: ' . $name)) {
                $found = true;
            }
            if ($found && '        run: |' === $line) {
                $collecting = true;
                continue;
            }
            if ($collecting) {
                if (!str_starts_with($line, '          ')) {
                    break;
                }
                $script[] = substr($line, 10);
            }
        }
        self::assertNotEmpty($script, $name);

        return implode("\n", $script);
    }

    /** Runs synthetic event variables through a bounded shell with no network or write commands. */
    private function guardExitCode(string $script, array $environment): int
    {
        return (new Process(['bash', '-euo', 'pipefail', '-c', $script], env: $environment, timeout: 10))->run();
    }
}
