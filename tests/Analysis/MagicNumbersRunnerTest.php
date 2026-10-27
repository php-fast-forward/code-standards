<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Analysis;

use FastForward\CodeStandards\Analysis\MagicNumbersRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/** Verifies process authority and bounded arguments independently of native timing/presentation. */
final class MagicNumbersRunnerTest extends TestCase
{
    /** Oversized arguments fail before execution, without truncation or partial reporting. */
    public function testOversizedInvocationNeverStartsTheTool(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('run');
        $runner = new MagicNumbersRunner($process, new BufferedOutput(), '/owned/vendor/povils/phpmnd/bin/phpmnd');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds 64 KiB');
        $runner->run(['/' . str_repeat('a', 65536) . '.php'], 'xml', '0,1', null, null);
    }

    /** Native options cannot supply a different cwd, autoloader, scanner directory or report path. */
    public function testNativeStatusAndExplicitFileArgumentsArePreserved(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $entrypoint = '/owned/vendor/povils/phpmnd/bin/phpmnd';
        $process->expects(self::once())->method('run')->with(
            [PHP_BINARY, $entrypoint, '--no-ansi', '--ignore-numbers=0,1', '--', '/consumer/a file.php', "/consumer/new\nline.php"],
            '/owned/vendor/povils/phpmnd',
        )->willReturn(1);
        $runner = new MagicNumbersRunner($process, new BufferedOutput(), $entrypoint);
        self::assertSame(1, $runner->run(['/consumer/a file.php', "/consumer/new\nline.php"], 'text', '0,1', null, null));
    }

    /** Invalid path encoding is rejected before file IO, including on hosts whose filesystem cannot create such names. */
    public function testXmlInvalidPathsFailBeforeExecutionOrOutput(): void
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('run');
        $output = new BufferedOutput();
        $runner = new MagicNumbersRunner($process, $output, '/owned/vendor/povils/phpmnd/bin/phpmnd');
        foreach (["/consumer/control\x01.php", "/consumer/encoding\xFF.php"] as $path) {
            try {
                $runner->run([$path], 'xml', '0,1', null, null);
                self::fail('Invalid XML path bytes must be rejected before file IO.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('selected path', $exception->getMessage());
                self::assertSame('', $output->fetch());
            }
        }
    }
}
