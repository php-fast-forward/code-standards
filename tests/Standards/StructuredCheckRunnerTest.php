<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Standards;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Standards\EcsJsonReportParser;
use FastForward\CodeStandards\Standards\StructuredCheckRunner;
use PHPUnit\Framework\TestCase;

/** Checks phase boundaries, native status preservation and explicit partial coverage. */
final class StructuredCheckRunnerTest extends TestCase
{
    public function testEmptySelectionRunsNothing(): void
    {
        $process = new QueuedReportProcess();
        $result = $this->runner($process)->run('/repo', [], '/config');
        self::assertSame('clean', $result->status);
        self::assertTrue($result->complete);
        self::assertSame([], $process->commands);
    }

    public function testSyntaxFailurePreventsEcsAndRetainsStatusWithoutParsingDiagnostics(): void
    {
        $process = new QueuedReportProcess(syntaxResult: new ProcessResult(255, 'native diagnostic', 'native stderr'));
        $result = $this->runner($process)->run('/repo', ['/first.php', '/next.php'], '/config');
        self::assertSame('syntax-failed', $result->status);
        self::assertSame(255, $result->exitCode);
        self::assertFalse($result->complete);
        self::assertSame(1, $result->syntaxChecked);
        self::assertSame(2, $result->toArray()['selection']['style_remaining']);
        self::assertCount(1, $process->commands);
    }

    public function testFindingsStopAtTheFirstFailingBoundedBatch(): void
    {
        $files = array_map(static fn(int $number): string => '/repo/' . $number . '.php', range(1, 101));
        $findings = ['totals' => ['errors' => 0, 'diffs' => 1], 'files' => [$files[50] => ['diffs' => [['diff' => 'native diff', 'applied_checkers' => ['Fixer']]]]]];
        $process = new QueuedReportProcess([
            new ProcessResult(0, '{"totals":{"errors":0,"diffs":0},"files":[]}', ''),
            new ProcessResult(2, json_encode($findings, JSON_THROW_ON_ERROR), ''),
        ]);
        $result = $this->runner($process)->run('/repo', $files, '/config');
        self::assertSame('findings', $result->status);
        self::assertSame(2, $result->exitCode);
        self::assertFalse($result->complete);
        self::assertSame(101, $result->syntaxChecked);
        self::assertSame(100, $result->styleSubmitted);
        self::assertSame(1, $result->toArray()['selection']['style_remaining']);
        self::assertCount(103, $process->commands);
        self::assertContains('--format=json', $process->commands[101]);
        self::assertNotContains('--fix', $process->commands[101]);
    }

    public function testCleanBatchesAccountForEverySelectedFile(): void
    {
        foreach ([1, 50, 51, 100, 101] as $count) {
            $files = array_map(static fn(int $number): string => '/repo/' . $number . '.php', range(1, $count));
            $process = new QueuedReportProcess();
            $result = $this->runner($process)->run('/repo', $files, '/config');
            self::assertSame('clean', $result->status);
            self::assertTrue($result->complete);
            self::assertSame($count, $result->syntaxChecked);
            self::assertSame($count, $result->styleSubmitted);
            self::assertSame(0, $result->toArray()['selection']['style_remaining']);
            self::assertCount($count + (int) ceil($count / 50), $process->commands);
        }
    }

    public function testOperationalErrorsCannotBecomeFindings(): void
    {
        foreach ([new \OverflowException('too much output'), new ProcessResult(2, '{"totals":{"errors":1,"diffs":0},"files":[]}', 'native failure')] as $outcome) {
            $result = $this->runner(new QueuedReportProcess([$outcome]))->run('/repo', ['/repo/source.php'], '/config');
            self::assertSame('operational-error', $result->status);
            self::assertSame(1, $result->exitCode);
            self::assertFalse($result->complete);
            self::assertSame(0, $result->styleSubmitted);
            self::assertSame([], $result->files);
        }
    }

    private function runner(QueuedReportProcess $process): StructuredCheckRunner
    {
        return new StructuredCheckRunner($process, '/worker', '/autoload', '/ecs', new EcsJsonReportParser());
    }
}
