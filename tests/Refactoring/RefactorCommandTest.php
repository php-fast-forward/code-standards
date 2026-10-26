<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Refactoring;

use FastForward\CodeStandards\Console\Command\RefactorCommand;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/** Exercises real attribute binding without loading Rector into the parent process. */
final class RefactorCommandTest extends TestCase
{
    /** Missing explicit config fails before native transport; difference status remains observable. */
    public function testNativeBindingAndRequiredConfiguration(): void
    {
        $config = tempnam(sys_get_temp_dir(), 'rector-policy-');
        $directory = realpath(sys_get_temp_dir());
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::once())->method('capture')->willReturn('{"rectors":["ReviewedRule"]}');
        $process->expects(self::once())->method('run')->with(self::callback(static fn(array $arguments): bool => in_array('--dry-run', $arguments, true)), $directory)->willReturn(2);
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new RefactorCommand(new ProjectScopeResolver('/unused-default'), new RectorRunner($process, '/tool/rector')));
        $tester = new ApplicationTester($application);

        try {
            self::assertSame(2, $tester->run(['command' => 'refactor']));
            self::assertStringContainsString('explicit native Rector configuration', $tester->getDisplay());
            self::assertSame(2, $tester->run(['command' => 'refactor', '--config' => $config, '--directory' => $directory]));
            self::assertStringNotContainsString('ERROR', $tester->getDisplay());
        } finally {
            unlink($config);
        }
    }

    /** Captured preflight configuration receives the same credential stripping as streamed execution. */
    public function testCapturedConfigurationDoesNotReceiveToolCredentials(): void
    {
        $previous = [];
        foreach (['GH_TOKEN', 'GITHUB_TOKEN', 'COMPOSER_AUTH'] as $name) {
            $previous[$name] = getenv($name);
            putenv($name . '=synthetic-test-value');
        }

        try {
            $output = (new NativeProcessRunner())->capture([
                PHP_BINARY, '-r', 'foreach (["GH_TOKEN", "GITHUB_TOKEN", "COMPOSER_AUTH"] as $name) { echo getenv($name) === false ? "absent\n" : "present\n"; }',
            ], sys_get_temp_dir());
            self::assertSame("absent\nabsent\nabsent\n", $output);
        } finally {
            foreach ($previous as $name => $value) {
                putenv(false === $value ? $name : $name . '=' . $value);
            }
        }
    }
}
