<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Project\ProjectDirectoryResolver;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Workflow\BoundedProcess;
use FastForward\CodeStandards\Workflow\NativeReportParser;
use FastForward\CodeStandards\Workflow\OptionalAnalysisRunner;
use FastForward\CodeStandards\Workflow\WorkflowInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

foreach (['AnalysisResult', 'BoundedProcess', 'NativeReportParser', 'WorkflowInput', 'OptionalAnalysisRunner'] as $helper) {
    require_once dirname(__DIR__, 2) . '/.github/OptionalAnalysis/' . $helper . '.php';
}

/** Verifies explicit enablement and the distinction between disabled and genuinely empty selection. */
final class OptionalAnalysisInputTest extends TestCase
{
    /** Disabled tools must not open Git or start a process merely to produce their status. */
    public function testBothDisabledDoNotResolveAProjectOrExecuteAnything(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $process->expects(self::never())->method('run');
        $runner = $this->runner($process);
        $results = $runner->run($this->input('/directory-that-does-not-exist', false));
        self::assertSame(['disabled', 'disabled'], array_column($results, 'status'));
    }

    /** A valid Git selection with no PHP produces not-executed, never a fabricated engine-clean result. */
    public function testEmptySelectionDoesNotStartTheAnalyzer(): void
    {
        $directory = realpath(sys_get_temp_dir());
        $process = $this->createMock(ProcessRunner::class);
        $process->method('capture')->willReturnCallback(static function (array $arguments): string {
            return match (true) {
                in_array('--is-inside-work-tree', $arguments, true) => "true\n",
                in_array('rev-parse', $arguments, true) && in_array('HEAD', $arguments, true) => str_repeat('b', 40) . "\n",
                default => '',
            };
        });
        $process->expects(self::never())->method('run');
        $results = $this->runner($process)->run($this->input($directory, true));
        self::assertSame('not-executed', $results[0]->status);
        self::assertSame(0, $results[0]->selectedCount);
        self::assertNull($results[0]->findingCount);
        self::assertNull($results[0]->nativeExit);
        self::assertSame('disabled', $results[1]->status);
    }

    /** Unavailable checkout prerequisites are errors even when findings would be advisory. */
    public function testUnavailableConsumerCannotBecomeCleanOrAdvisoryFindings(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $results = $this->runner($process)->run($this->input('/directory-that-does-not-exist', true));
        self::assertSame('operational-error', $results[0]->status);
        self::assertTrue($results[0]->blocks('report'));
        self::assertNull($results[0]->findingCount);
    }

    /** Missing/stringly enablement and unrecognized severity policies fail instead of being coerced. */
    public function testEnvironmentInputsAreExplicitAndStrict(): void
    {
        $environment = [
            'STANDARDS_DIRECTORY' => '/consumer', 'STANDARDS_BASE' => str_repeat('a', 40), 'STANDARDS_HEAD' => str_repeat('b', 40),
            'STANDARDS_MAGIC_NUMBERS' => 'false', 'STANDARDS_MAGIC_NUMBERS_POLICY' => 'report',
            'STANDARDS_RECTOR' => 'false', 'STANDARDS_RECTOR_POLICY' => 'report', 'STANDARDS_RECTOR_CONFIG' => '',
        ];
        self::assertFalse(WorkflowInput::fromEnvironment($environment)->magicNumbers);
        foreach (['STANDARDS_MAGIC_NUMBERS' => 'yes', 'STANDARDS_MAGIC_NUMBERS_POLICY' => 'ignore', 'STANDARDS_HEAD' => 'main'] as $key => $value) {
            try {
                WorkflowInput::fromEnvironment([...$environment, $key => $value]);
                self::fail('Malformed optional input must not be coerced.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    /** The fake installation is deliberately absent: tests which accidentally launch it must fail. */
    private function runner(ProcessRunner $process): OptionalAnalysisRunner
    {
        return new OptionalAnalysisRunner(new FileSelection($process), new PhpFiles(), new ProjectScopeResolver('/unused', new ProjectDirectoryResolver()), new BoundedProcess(), new NativeReportParser(), '/nonexistent-tool');
    }

    /** Produces only explicit fixture options; no native analysis executes inside this test class. */
    private function input(string $directory, bool $magic): WorkflowInput
    {
        return new WorkflowInput($directory, str_repeat('a', 40), str_repeat('b', 40), $magic, 'report', false, 'report', '');
    }
}
