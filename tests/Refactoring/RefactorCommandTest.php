<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Refactoring;

use FastForward\CodeStandards\Console\Command\RefactorCommand;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

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

    /** Both channels preserve credential filtering when a caller supplies a finite timeout override. */
    public function testConfigurationSubprocessesDoNotReceiveToolCredentials(): void
    {
        $previous = [];
        foreach (['GH_TOKEN', 'GITHUB_TOKEN', 'COMPOSER_AUTH'] as $name) {
            $previous[$name] = getenv($name);
            putenv($name . '=synthetic-test-value');
        }

        try {
            $arguments = [
                PHP_BINARY, '-r', 'foreach (["GH_TOKEN", "GITHUB_TOKEN", "COMPOSER_AUTH"] as $name) { echo getenv($name) === false ? "absent\n" : "present\n"; }',
            ];
            $output = (new NativeProcessRunner())->capture($arguments, sys_get_temp_dir(), 2);
            self::assertSame("absent\nabsent\nabsent\n", $output);
            $streamed = '';
            $process = new NativeProcessRunner(static function (string $type, string $bytes) use (&$streamed): void {
                $streamed .= $bytes;
            });
            self::assertSame(0, $process->run($arguments, sys_get_temp_dir(), 2));
            self::assertSame("absent\nabsent\nabsent\n", $streamed);
        } finally {
            foreach ($previous as $name => $value) {
                putenv(false === $value ? $name : $name . '=' . $value);
            }
        }
    }

    /** An actual short subprocess timeout is an execution failure, with the preflight cleaned on the same exception path. */
    public function testTimeoutIsFailureAndCleansThePreflight(): void
    {
        $native = new Process([PHP_BINARY, '-r', 'usleep(250000);'], timeout: 0.01);
        try {
            $native->mustRun();
            self::fail('The controlled subprocess must time out.');
        } catch (ProcessTimedOutException $failure) {
            $timeout = $failure;
        }
        $config = tempnam(sys_get_temp_dir(), 'rector-policy-');
        $preflight = null;
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::once())->method('capture')->willReturnCallback(static function (array $arguments) use (&$preflight, $timeout): never {
            $preflight = substr($arguments[3], strlen('--config='));
            throw $timeout;
        });
        $process->expects(self::never())->method('run');
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new RefactorCommand(new ProjectScopeResolver('/unused-default'), new RectorRunner($process, '/tool/rector')));
        $tester = new ApplicationTester($application);

        try {
            self::assertSame(1, $tester->run(['command' => 'refactor', '--config' => $config, '--directory' => realpath(sys_get_temp_dir())]));
            self::assertStringContainsString('exceeded the timeout', $tester->getDisplay());
            self::assertIsString($preflight);
            self::assertFileDoesNotExist($preflight);
            self::assertDirectoryDoesNotExist(dirname($preflight));
        } finally {
            unlink($config);
        }
    }

    /** @return iterable<string, array{string}> Inputs that must never be coerced to a disabled or unintended limit. */
    public static function invalidTimeouts(): iterable
    {
        foreach (['bad', '1.5', '0.5', '0', '-1', '1e3', 'INF', 'NAN', ' 5', '5 ', '+5', '01', '999999999999999999999'] as $input) {
            yield $input => [$input];
        }
    }

    /** Rejects malformed bounds through the actual Symfony option binding before touching transport. */
    #[DataProvider('invalidTimeouts')]
    public function testInvalidTimeoutIsRejectedWithoutLaunchingProcesses(string $timeout): void
    {
        $config = tempnam(sys_get_temp_dir(), 'rector-policy-');
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $process->expects(self::never())->method('run');
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new RefactorCommand(new ProjectScopeResolver('/unused-default'), new RectorRunner($process, '/tool/rector')));
        $tester = new ApplicationTester($application);
        try {
            self::assertSame(2, $tester->run(['command' => 'refactor', '--config' => $config, '--timeout' => $timeout]));
            self::assertStringContainsString('--timeout must be a positive whole number', $tester->getDisplay());
        } finally {
            unlink($config);
        }
    }

    /** The actual option binding forwards the requested positive limit to the native preflight and dry-run. */
    public function testPositiveTimeoutOptionIsForwardedWithoutCoercion(): void
    {
        $config = tempnam(sys_get_temp_dir(), 'rector-policy-');
        $directory = realpath(sys_get_temp_dir());
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::once())->method('capture')->with(self::isArray(), $directory, 600.0)->willReturn('{"rectors":["ReviewedRule"]}');
        $process->expects(self::once())->method('run')->with(self::isArray(), $directory, 600.0)->willReturn(0);
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new RefactorCommand(new ProjectScopeResolver('/unused-default'), new RectorRunner($process, '/tool/rector')));
        $tester = new ApplicationTester($application);
        try {
            self::assertSame(0, $tester->run(['command' => 'refactor', '--config' => $config, '--directory' => $directory, '--timeout' => '600']));
        } finally {
            unlink($config);
        }
    }
}
