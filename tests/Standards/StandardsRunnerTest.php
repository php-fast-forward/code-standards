<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Standards;

use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Standards\StandardsRunner;
use PHPUnit\Framework\TestCase;

final class StandardsRunnerTest extends TestCase
{
    public function testOnlyExplicitFixAddsWriteFlagAndFilesRemainIndividualArguments(): void
    {
        foreach ([false, true] as $fix) {
            $process = new RecordingProcess();
            $files = ['/repo/a b.php', "/repo/c\nd.php", '/repo/-dash.php'];
            self::assertSame(0, (new StandardsRunner($process, '/tools/ecs'))->run('/repo', $files, '/tools/ecs.php', $fix));
            $command = $process->commands[3];
            self::assertSame($fix, in_array('--fix', $command, true));
            self::assertSame($files, array_slice($command, -3));
            self::assertSame([PHP_BINARY, '-n', '-l', $files[0]], $process->commands[0]);
        }
    }

    public function testBatchesAreBoundedAndEcsFailureIsNotSwallowed(): void
    {
        $process = new RecordingProcess(23);
        $files = array_map(static fn (int $i): string => '/repo/' . $i . '.php', range(1, 101));
        self::assertSame(23, (new StandardsRunner($process, '/tools/ecs'))->run('/repo', $files, '/config', false));
        self::assertCount(102, $process->commands);
        self::assertCount(57, $process->commands[101]);
    }

    public function testSyntaxFailurePreventsFixAndNoFilesExecutesNothing(): void
    {
        $process = new RecordingProcess(0, 17);
        $runner = new StandardsRunner($process, '/tools/ecs');
        self::assertSame(0, $runner->run('/repo', [], '/config', true));
        self::assertSame([], $process->commands);
        self::assertSame(17, $runner->run('/repo', ['/repo/a.php'], '/config', true));
        self::assertCount(1, $process->commands);
    }
}

final class RecordingProcess implements ProcessRunner
{
    public array $commands = [];

    public function __construct(private readonly int $ecsStatus = 0, private readonly int $syntaxStatus = 0)
    {
    }

    public function capture(array $arguments, string $directory): string
    {
        throw new \LogicException('Not expected.');
    }

    public function run(array $arguments, string $directory): int
    {
        $this->commands[] = $arguments;

        return in_array('-l', $arguments, true) ? $this->syntaxStatus : $this->ecsStatus;
    }
}
