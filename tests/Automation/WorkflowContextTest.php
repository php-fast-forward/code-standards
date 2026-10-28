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
            'STANDARDS_JOB_JSON' => $this->identity(),
            'STANDARDS_BASE' => str_repeat('2', 40),
            'STANDARDS_HEAD' => str_repeat('3', 40),
        ];
        foreach (['code-standards-check.yml', 'code-standards-analysis.yml'] as $file) {
            $script = $this->guard($file, 'Reject privileged events and invalid refs');
            $valid = $this->guardResult($script, $environment);
            self::assertSame(0, $valid['status']);
            self::assertSame("repository=php-fast-forward/code-standards\nsha=" . str_repeat('1', 40) . "\n", $valid['output']);
            foreach (['STANDARDS_EVENT' => 'pull_request_target', 'STANDARDS_BASE' => 'main', 'STANDARDS_HEAD' => 'v0'] as $key => $value) {
                self::assertNotSame(0, $this->guardExitCode($script, [...$environment, $key => $value]), $file);
            }
        }
    }

    /** Accepts only full stable tags before invoking the external channel action. */
    public function testChannelGuardRejectsPartialPrereleaseAndInjectedTags(): void
    {
        $script = $this->guard('repository-release-channels.yml', 'Require a full stable release tag');
        foreach (['v0.1.0', 'v0.99.3', 'v1.0.0'] as $tag) {
            self::assertSame(0, $this->guardExitCode($script, ['RELEASE_TAG' => $tag]));
        }
        foreach (['v0', '0.1.0', 'v0.01.0', 'v1.0.0-rc1', 'v1.0.0+build', "v0.1.0\n", '$(exit 0)'] as $tag) {
            self::assertNotSame(0, $this->guardExitCode($script, ['RELEASE_TAG' => $tag]));
        }
    }

    /** Rejects malformed or ambiguous native identity protocols before emitting any checkout output. */
    public function testIdentityProtocolFailsClosedWithoutOutput(): void
    {
        $invalid = [
            'empty' => '', 'invalid JSON' => '{', 'missing identity' => '{}',
            'null' => 'null', 'array' => '[]', 'string' => '"job"',
            'multiple documents' => $this->identity() . "\n" . $this->identity(),
            'missing SHA' => '{"workflow_repository":"php-fast-forward/code-standards"}',
            'foreign repository' => $this->identity(['workflow_repository' => 'foreign/tool']),
            'repository type' => $this->identity(['workflow_repository' => 1]),
            'SHA type' => $this->identity(['workflow_sha' => 123]),
            'SHA array' => $this->identity(['workflow_sha' => []]),
            'moving ref' => $this->identity(['workflow_sha' => 'v0']),
            'short SHA' => $this->identity(['workflow_sha' => str_repeat('a', 39)]),
            'uppercase SHA' => $this->identity(['workflow_sha' => str_repeat('A', 40)]),
            'SHA newline' => $this->identity(['workflow_sha' => str_repeat('a', 40) . "\n"]),
            'output injection' => $this->identity(['workflow_sha' => "$(exit 0)\nrepository=foreign/tool"]),
        ];

        foreach ([
            ['code-standards-check.yml', 'Reject privileged events and invalid refs', 'STANDARDS_JOB_JSON', [
                'STANDARDS_EVENT' => 'pull_request', 'STANDARDS_BASE' => str_repeat('2', 40), 'STANDARDS_HEAD' => str_repeat('3', 40),
            ]],
            ['code-standards-analysis.yml', 'Reject privileged events and invalid refs', 'STANDARDS_JOB_JSON', [
                'STANDARDS_EVENT' => 'pull_request', 'STANDARDS_BASE' => str_repeat('2', 40), 'STANDARDS_HEAD' => str_repeat('3', 40),
            ]],
        ] as [$file, $step, $variable, $environment]) {
            $script = $this->guard($file, $step);
            foreach ($invalid as $scenario => $json) {
                $result = $this->guardResult($script, [...$environment, $variable => $json]);

                self::assertNotSame(0, $result['status'], $file . ': ' . $scenario);
                self::assertSame('', $result['output'], $file . ': ' . $scenario);
            }
        }
    }

    /**
     * Encodes a synthetic native job context without installing or contacting a GitHub runner.
     *
     * @param array<string, mixed> $overrides Explicit malformed protocol fields for negative cases.
     */
    private function identity(array $overrides = []): string
    {
        return json_encode(array_replace([
            'workflow_repository' => 'php-fast-forward/code-standards',
            'workflow_sha' => str_repeat('1', 40),
            'status' => 'success',
        ], $overrides), JSON_THROW_ON_ERROR);
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
        return $this->guardResult($script, $environment)['status'];
    }

    /**
     * Executes the actual guard with a private runner-output file.
     *
     * @param string $script The actual workflow step's executable body.
     * @param array<string, string> $environment Synthetic job/event variables, never live credentials.
     * @return array{status: int, output: string} Observable process result and emitted checkout outputs.
     */
    private function guardResult(string $script, array $environment): array
    {
        $output = tempnam(sys_get_temp_dir(), 'workflow-identity-');
        self::assertIsString($output);

        try {
            $process = new Process(['bash', '-euo', 'pipefail', '-c', $script], env: [...$environment, 'GITHUB_OUTPUT' => $output], timeout: 10);

            return ['status' => $process->run(), 'output' => file_get_contents($output)];
        } finally {
            unlink($output);
        }
    }
}
