<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Refactoring;

use FastForward\CodeStandards\Console\Command\RefactorCommand;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Project\ProjectDirectoryResolver;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/** Qualifies native JSON selection without evaluating consumer configuration in the test process. */
final class RectorJsonPreviewTest extends TestCase
{
    /** JSON keeps the native dry-run exit and adds no inventory or processing invocation. */
    public function testJsonPreviewUsesNativeOutputWithOneInventoryAndOneProcess(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::once())->method('capture')->willReturn('{"rectors":["ReviewedRule"]}');
        $process->expects(self::once())->method('run')->with(
            self::callback(static fn(array $arguments): bool => in_array('--dry-run', $arguments, true)
                && in_array('--output-format=json', $arguments, true)),
            '/consumer',
            15.0,
        )->willReturn(2);

        self::assertSame(2, (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/policy.php', false, 15, 'json'));
    }

    /** Invalid representation or JSON apply cannot evaluate configuration or grant write authority. */
    public function testInvalidOutputAndJsonApplyFailBeforeAnySubprocess(): void
    {
        foreach ([[false, 'xml'], [true, 'json']] as [$apply, $format]) {
            $process = $this->createMock(ProcessRunner::class);
            $process->expects(self::never())->method('capture');
            $process->expects(self::never())->method('run');

            try {
                (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/policy.php', $apply, format: $format);
                self::fail('An unsupported output/write combination must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /** The actual invocable attribute binding accepts JSON and rejects apply before native work. */
    public function testSymfonyBindingAndHelpDescribeTheJsonBoundary(): void
    {
        $config = tempnam(sys_get_temp_dir(), 'rector-json-config-');
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $process->expects(self::never())->method('run');
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new RefactorCommand(new ProjectScopeResolver('/unused', new ProjectDirectoryResolver()), new RectorRunner($process, '/tool/rector')));
        $tester = new ApplicationTester($application);

        try {
            self::assertSame(0, $tester->run(['command' => 'help', 'command_name' => 'refactor']));
            self::assertStringContainsString('--format', $tester->getDisplay());
            self::assertMatchesRegularExpression('/preview\s+only/', $tester->getDisplay());
            self::assertSame(2, $tester->run(['command' => 'refactor', '--config' => $config, '--format' => 'json', '--apply' => true]));
            self::assertStringContainsString('preview-only', $tester->getDisplay());
        } finally {
            unlink($config);
        }
    }
}
