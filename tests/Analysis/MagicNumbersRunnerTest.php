<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Analysis;

use Composer\InstalledVersions;
use DOMDocument;
use FastForward\CodeStandards\Analysis\MagicNumbersRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/** Verifies process authority and bounded arguments independently of native timing/presentation. */
final class MagicNumbersRunnerTest extends TestCase
{
    /** Empty text selection is an explicit no-op, without querying or starting a process. */
    public function testEmptyTextSelectionNeverStartsTheTool(): void
    {
        $output = new BufferedOutput();
        $runner = $this->runnerWithoutExecution($output);

        self::assertSame(0, $runner->run([], 'text', '0,1', null, null));
        self::assertSame('No PHP files selected.' . PHP_EOL, $output->fetch());
    }

    /** Empty XML exposes the installed engine identity without claiming an analyzer invocation. */
    public function testEmptyXmlSelectionNeverStartsTheTool(): void
    {
        $output = new BufferedOutput();
        $runner = $this->runnerWithoutExecution($output);

        self::assertSame(0, $runner->run([], 'xml', '0,1', null, null));
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($output->fetch(), LIBXML_NONET));
        $root = $document->documentElement;
        self::assertNotNull($root);
        self::assertSame('phpmnd', $root->tagName);
        self::assertSame(InstalledVersions::getPrettyVersion('povils/phpmnd'), $root->getAttribute('version'));
        self::assertSame('0', $root->getAttribute('fileCount'));
        self::assertSame('0', $root->getAttribute('errorCount'));
        self::assertSame(0, $document->getElementsByTagName('entry')->length);
        self::assertSame(1, $document->getElementsByTagName('files')->length);
    }

    /** Validation also applies to empty selections and cannot emit a false clean report. */
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsNeverStartTheTool(string $format, string $numbers, ?string $extensions, string $diagnostic): void
    {
        $output = new BufferedOutput();
        $runner = $this->runnerWithoutExecution($output);

        try {
            $runner->run([], $format, $numbers, null, $extensions);
            self::fail('Invalid analysis options must not return a clean empty result.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString($diagnostic, $exception->getMessage());
        }

        self::assertSame('', $output->fetch());
    }

    /** @return iterable<string, array{string, string, string|null, string}> Invalid public options and their diagnostic contracts. */
    public static function invalidOptions(): iterable
    {
        yield 'unsupported format' => ['json', '0,1', null, 'Format must be text or xml'];
        yield 'empty format' => ['', '0,1', null, 'Format must be text or xml'];
        yield 'not a number' => ['text', 'invalid', null, 'finite numeric values'];
        yield 'not a finite number' => ['text', 'NAN', null, 'finite numeric values'];
        yield 'positive infinity' => ['text', 'INF', null, 'finite numeric values'];
        yield 'negative infinity' => ['text', '-INF', null, 'finite numeric values'];
        yield 'overflowing number' => ['text', '1e9999', null, 'finite numeric values'];
        yield 'empty numeric entry' => ['text', '0,1,', null, 'finite numeric values'];
        yield 'empty extensions' => ['text', '0,1', '', 'name native PHPMND analysis contexts'];
        yield 'empty extension entry' => ['text', '0,1', 'all,,argument', 'name native PHPMND analysis contexts'];
        yield 'unknown extension' => ['text', '0,1', 'missing-context', 'does not exist'];
    }

    /** XML output and native policy overrides remain literal arguments in one bounded invocation. */
    public function testXmlOptionsAndNativeStatusArePreserved(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'standards-mnd-unit-');
        self::assertNotFalse($file);
        $source = '<?php if ($value === 42) {}' . PHP_EOL;
        file_put_contents($file, $source);
        $process = $this->createMock(ProcessRunner::class);
        $entrypoint = '/owned/vendor/povils/phpmnd/bin/phpmnd';
        $process->expects(self::never())->method('capture');
        $process->expects(self::once())->method('run')->with(
            [
                PHP_BINARY,
                $entrypoint,
                '--no-ansi',
                '--ignore-numbers=42',
                '--ignore-funcs=output,fixture',
                '--extensions=all,-argument',
                '--quiet',
                '--xml-output=php://stdout',
                '--',
                $file,
            ],
            '/owned/vendor/povils/phpmnd',
        )->willReturn(17);
        $output = new BufferedOutput();
        $runner = new MagicNumbersRunner($process, $output, $entrypoint);

        try {
            self::assertSame(17, $runner->run([$file], 'xml', '42', 'output,fixture', 'all,-argument'));
            self::assertSame('', $output->fetch());
            self::assertSame($source, file_get_contents($file));
        } finally {
            unlink($file);
        }
    }

    /** A missing file exercises real read failure without relying on platform-specific permission behavior. */
    public function testMissingXmlSourceFailsBeforeExecutionOrOutput(): void
    {
        $file = sys_get_temp_dir() . '/standards-mnd-missing-' . bin2hex(random_bytes(8)) . '.php';
        self::assertFileDoesNotExist($file);
        $output = new BufferedOutput();
        $runner = $this->runnerWithoutExecution($output);

        try {
            $runner->run([$file], 'xml', '0,1', null, null);
            self::fail('An unreadable XML source must not return a clean result.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('could not read a selected source', $exception->getMessage());
        }

        self::assertSame('', $output->fetch());
        self::assertFileDoesNotExist($file);
    }

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

    /** Both transport methods must remain unused for rejected inputs and empty selections. */
    private function runnerWithoutExecution(BufferedOutput $output): MagicNumbersRunner
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $process->expects(self::never())->method('run');

        return new MagicNumbersRunner($process, $output, '/owned/vendor/povils/phpmnd/bin/phpmnd');
    }
}
