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
            'STANDARDS_RUNNER_OS' => 'Linux',
            'STANDARDS_EXECUTABLE_CONFIG' => '', 'STANDARDS_RECTOR_ENABLED' => 'false',
            'STANDARDS_RUNNER_ENVIRONMENT' => 'github-hosted',
            'STANDARDS_HEAD_REPOSITORY' => 'consumer/cli',
            'STANDARDS_REPOSITORY' => 'consumer/cli',
            'STANDARDS_EVENT' => 'pull_request',
            'STANDARDS_JOB_JSON' => $this->identity(),
            'STANDARDS_BASE' => str_repeat('2', 40),
            'STANDARDS_HEAD' => str_repeat('3', 40),
        ];
        foreach (['code-standards-check.yml', 'code-standards-analysis.yml'] as $file) {
            $script = $this->guard($file, 'Reject privileged events and invalid refs');
            $valid = $this->guardResult($script, $environment);
            self::assertSame(0, $valid['status']);
            self::assertSame(0, $this->guardExitCode($script, [...$environment, 'STANDARDS_RUNNER_ENVIRONMENT' => 'self-hosted']));
            self::assertNotSame(0, $this->guardExitCode($script, [...$environment, 'STANDARDS_RUNNER_ENVIRONMENT' => 'self-hosted', 'STANDARDS_HEAD_REPOSITORY' => 'fork/cli']));
            self::assertNotSame(0, $this->guardExitCode($script, [...$environment, 'STANDARDS_RUNNER_ENVIRONMENT' => 'self-hosted', 'STANDARDS_REPOSITORY' => '', 'STANDARDS_HEAD_REPOSITORY' => '']));
            self::assertSame(0, $this->guardExitCode($script, [...$environment, 'STANDARDS_HEAD_REPOSITORY' => 'fork/cli']));
            foreach (['Windows', 'macOS', ''] as $platform) {
                $invalid = $this->guardResult($script, [...$environment, 'STANDARDS_RUNNER_OS' => $platform]);
                self::assertNotSame(0, $invalid['status']);
                self::assertSame('', $invalid['output']);
            }
            foreach (['', 'unknown'] as $runnerEnvironment) {
                $invalid = $this->guardResult($script, [...$environment, 'STANDARDS_RUNNER_ENVIRONMENT' => $runnerEnvironment]);
                self::assertNotSame(0, $invalid['status']);
                self::assertSame('', $invalid['output']);
            }
            $execution = 'code-standards-check.yml' === $file
                ? ['STANDARDS_EXECUTABLE_CONFIG' => 'consumer-config.php']
                : ['STANDARDS_RECTOR_ENABLED' => 'true'];
            self::assertSame(0, $this->guardExitCode($script, [...$environment, ...$execution]));
            $blocked = $this->guardResult($script, [...$environment, ...$execution, 'STANDARDS_RUNNER_ENVIRONMENT' => 'self-hosted']);
            self::assertNotSame(0, $blocked['status']);
            self::assertSame('', $blocked['output']);
            self::assertSame("repository=php-fast-forward/code-standards\nsha=" . str_repeat('1', 40) . "\n", $valid['output']);
            foreach (['STANDARDS_EVENT' => 'pull_request_target', 'STANDARDS_BASE' => 'main', 'STANDARDS_HEAD' => 'v0'] as $key => $value) {
                self::assertNotSame(0, $this->guardExitCode($script, [...$environment, $key => $value]), $file);
            }
        }
    }

    /** Exercises the actual inline release selection without contacting GitHub or mutating refs. */
    public function testReleaseSelectionPreventsOlderMajorAndGlobalPromotions(): void
    {
        $script = $this->guard('repository-release-channels.yml', 'Select the newest stable release for its major');
        $directory = sys_get_temp_dir() . '/release-selection-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        file_put_contents($directory . '/gh', "#!/bin/sh\ncat \"\$FF_TAG_FIXTURE\"\n");
        chmod($directory . '/gh', 0755);
        $environment = ['RUNNER_TEMP' => $directory, 'GITHUB_REPOSITORY' => 'fixture/repo',
            'FF_TAG_FIXTURE' => $directory . '/tags', 'PATH' => $directory . PATH_SEPARATOR . getenv('PATH')];
        try {
            foreach ([
                ['v0.4.1', ['v0.4.1', 'v0.4.2', 'v1.0.0'], false, false],
                ['v1.0.0', ['v1.0.0', 'v1.0.1'], false, false],
                ['v0.4.3', ['v0.4.2', 'v0.4.3', 'v1.0.0'], true, false],
                ['v1.0.0', ['v0.4.2', 'v1.0.0'], true, true],
                ['v0.10.0', ['v0.9.0', 'v0.10.0'], true, true],
                ['v0.4.2', ['v0', 'v1', 'latest', 'v0.4.2'], true, true],
            ] as [$tag, $tags, $promote, $latest]) {
                file_put_contents($directory . '/tags', implode("\n", $tags) . "\n");
                $result = $this->guardResult($script, [...$environment, 'FF_RELEASE_TAG' => $tag]);
                self::assertSame(0, $result['status']);
                self::assertSame('promote=' . ($promote ? 'true' : 'false') . "\nlatest=" . ($latest ? 'true' : 'false') . "\n", $result['output']);
            }
            foreach (['v0', '0.4.2', 'v0.04.2', 'v0.4.2-rc1', 'v0.4.2+build', "v0.4.2\n", '$(exit 0)'] as $tag) {
                $result = $this->guardResult($script, [...$environment, 'FF_RELEASE_TAG' => $tag]);
                self::assertNotSame(0, $result['status']);
                self::assertSame('', $result['output']);
            }
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($directory);
        }
    }

    /** An update may be handed to the writer only after all source and runtime jobs succeeded. */
    public function testCompiledUpdateRejectsFailedSkippedOrCancelledQualification(): void
    {
        $script = $this->guard('repository-ci.yml', 'Require successful source and artifact qualification');
        $valid = ['BUILD' => 'success', 'TESTS' => 'success', 'RUNTIME' => 'success'];
        self::assertSame(0, $this->guardResult($script, $valid)['status']);
        foreach (array_keys($valid) as $job) {
            foreach (['failure', 'skipped', 'cancelled', ''] as $result) {
                self::assertNotSame(0, $this->guardResult($script, [...$valid, $job => $result])['status'], $job . ': ' . $result);
            }
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
                'STANDARDS_RUNNER_OS' => 'Linux',
                'STANDARDS_EXECUTABLE_CONFIG' => '', 'STANDARDS_RECTOR_ENABLED' => 'false',
                'STANDARDS_RUNNER_ENVIRONMENT' => 'github-hosted',
                'STANDARDS_HEAD_REPOSITORY' => 'consumer/cli', 'STANDARDS_REPOSITORY' => 'consumer/cli',
                'STANDARDS_EVENT' => 'pull_request', 'STANDARDS_BASE' => str_repeat('2', 40), 'STANDARDS_HEAD' => str_repeat('3', 40),
            ]],
            ['code-standards-analysis.yml', 'Reject privileged events and invalid refs', 'STANDARDS_JOB_JSON', [
                'STANDARDS_RUNNER_OS' => 'Linux',
                'STANDARDS_EXECUTABLE_CONFIG' => '', 'STANDARDS_RECTOR_ENABLED' => 'false',
                'STANDARDS_RUNNER_ENVIRONMENT' => 'github-hosted',
                'STANDARDS_HEAD_REPOSITORY' => 'consumer/cli', 'STANDARDS_REPOSITORY' => 'consumer/cli',
                'STANDARDS_EVENT' => 'pull_request', 'STANDARDS_BASE' => str_repeat('2', 40), 'STANDARDS_HEAD' => str_repeat('3', 40),
            ]],
        ] as [$file, $step, $variable, $environment]) {
            $script = $this->guard($file, $step);
            if ('STANDARDS_JOB_JSON' === $variable) {
                $valid = $this->guardResult($script, [...$environment, $variable => $this->identity()]);
                self::assertSame(0, $valid['status'], $file . ': valid control');
                self::assertStringContainsString('repository=php-fast-forward/code-standards', $valid['output']);
            }
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
