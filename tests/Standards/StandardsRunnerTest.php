<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Standards;

use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Standards\StandardsRunner;
use PHPUnit\Framework\TestCase;

final class StandardsRunnerTest extends TestCase
{
    /** Proves write authority comes only from explicit fix and paths remain separate arguments. */
    public function testOnlyExplicitFixAddsWriteFlagAndFilesRemainIndividualArguments(): void
    {
        foreach ([false, true] as $fix) {
            $process = new RecordingProcess();
            $files = ['/repo/a b.php', "/repo/c\nd.php", '/repo/-dash.php'];
            self::assertSame(0, (new StandardsRunner($process, '/tools/worker', '/tools/autoload.php', '/tools/ecs'))->run('/repo', $files, '/tools/ecs.php', $fix));
            $command = $process->commands[3];
            self::assertSame($fix, in_array('--fix', $command, true));
            self::assertSame($files, array_slice($command, -3));
            self::assertSame([PHP_BINARY, '-n', '-l', $files[0]], $process->commands[0]);
        }
    }

    /** Verifies batch bounds and immediate propagation of a failed style subprocess. */
    public function testBatchesAreBoundedAndEcsFailureIsNotSwallowed(): void
    {
        $process = new RecordingProcess(23);
        $files = array_map(static fn(int $i): string => '/repo/' . $i . '.php', range(1, 101));
        self::assertSame(23, (new StandardsRunner($process, '/tools/worker', '/tools/autoload.php', '/tools/ecs'))->run('/repo', $files, '/config', false));
        self::assertCount(102, $process->commands);
        self::assertCount(57, $process->commands[101]);
    }

    /** Propagates residual style failures after explicit fix without silently applying another write pass. */
    public function testFixSuccessIsVerifiedWithoutAnotherWritePass(): void
    {
        $process = new RecordingProcess(verificationStatus: 19);
        $runner = new StandardsRunner($process, '/tools/worker', '/tools/autoload.php', '/tools/ecs');

        self::assertSame(19, $runner->run('/repo', ['/repo/a b.php'], '/config', true));
        self::assertCount(3, $process->commands);
        self::assertContains('--fix', $process->commands[1]);
        self::assertNotContains('--fix', $process->commands[2]);
        self::assertSame('/repo/a b.php', $process->commands[2][array_key_last($process->commands[2])]);
    }

    /** Rejects malformed source before writes and makes empty selection a subprocess-free no-op. */
    public function testSyntaxFailurePreventsFixAndNoFilesExecutesNothing(): void
    {
        $process = new RecordingProcess(0, 17);
        $runner = new StandardsRunner($process, '/tools/worker', '/tools/autoload.php', '/tools/ecs');
        self::assertSame(0, $runner->run('/repo', [], '/config', true));
        self::assertSame([], $process->commands);
        self::assertSame(17, $runner->run('/repo', ['/repo/a.php'], '/config', true));
        self::assertCount(1, $process->commands);
    }
}

final class RecordingProcess implements ProcessRunner
{
    public array $commands = [];

    /** Configures deterministic transport outcomes without executing real tools. */
    public function __construct(
        private readonly int $ecsStatus = 0,
        private readonly int $syntaxStatus = 0,
        private readonly ?int $verificationStatus = null,
    ) {}

    /** Fails if standards orchestration unexpectedly performs a capture operation. */
    public function capture(array $arguments, string $directory, float $timeout = 120): string
    {
        throw new \LogicException('Not expected.');
    }

    /** Records argument boundaries and returns the selected synthetic tool status. */
    public function run(array $arguments, string $directory, float $timeout = 300): int
    {
        $this->commands[] = $arguments;

        if (in_array('-l', $arguments, true)) {
            return $this->syntaxStatus;
        }

        return !in_array('--fix', $arguments, true) && null !== $this->verificationStatus
            ? $this->verificationStatus : $this->ecsStatus;
    }
}
