<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises the real attributed command, native analyzer and Git selection in synthetic projects. */
final class MagicNumbersTest extends TestCase
{
    private string $directory;

    /** Creates an exclusive disposable Git checkout; no application or consumer dependencies are installed. */
    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-mnd-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->put('.gitignore', "/vendor/\n/generated/\n");
        $this->git(['init', '--quiet']);
        $this->git(['config', 'user.email', 'fixture@example.test']);
        $this->git(['config', 'user.name', 'Synthetic fixture']);
    }

    /** Removes only the unique fixture owned by this test. */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Native XML remains deterministic, round-trips binary-safe names and never executes implicit consumer bootstrap. */
    public function testNativeXmlPreservesPathsAndSourceWithoutConsumerAutoload(): void
    {
        $source = '<?php if ($value === 42) { echo "found"; }' . "\n";
        $paths = ['src/a file.php', "src/new\nline.php", 'src/café.php'];
        foreach ($paths as $path) {
            $this->put($path, $source);
        }
        $this->put('generated/ignored.php', $source);
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../executed", "unexpected");');
        $this->put('composer.lock', 'synthetic lock');
        $this->git(['add', '--all']);
        $this->git(['add', '-f', '--', 'generated/ignored.php', 'vendor/autoload.php']);

        $result = $this->cli(['--all', '--format=xml']);
        self::assertSame(1, $result->getExitCode(), $result->getErrorOutput());
        self::assertSame('', $result->getErrorOutput());
        self::assertStringNotContainsString("\033", $result->getOutput());
        $document = $this->xml($result->getOutput());
        $reported = [];
        foreach ($document->getElementsByTagName('file') as $file) {
            $reported[] = $file->getAttribute('path');
        }
        sort($paths, SORT_STRING);
        self::assertSame(array_map(fn(string $path): string => $this->directory . '/' . $path, $paths), $reported);
        self::assertSame('3', $this->errorCount($document));
        self::assertSame($result->getOutput(), $this->cli(['--all', '--format=xml'])->getOutput());
        foreach ($paths as $path) {
            self::assertSame($source, file_get_contents($this->directory . '/' . $path));
        }
        self::assertSame('synthetic lock', file_get_contents($this->directory . '/composer.lock'));
        self::assertFileDoesNotExist($this->directory . '/executed');
    }

    /** Native numeric/context/function overrides remain explicit, with no formatter configuration involved. */
    public function testOverridesAndTextStatus(): void
    {
        $this->put('source.php', '<?php if ($value === 42) { echo "found"; }' . "\n");
        $this->git(['add', '--all']);
        self::assertSame(1, $this->cli(['--all'])->getExitCode());
        $override = $this->cli(['--all', '--format=xml', '--ignore-numbers=0,1,42']);
        self::assertSame(0, $override->getExitCode(), $override->getErrorOutput());
        self::assertSame('0', $this->errorCount($this->xml($override->getOutput())));

        $this->put('source.php', '<?php output(42);' . "\n");
        self::assertSame(0, $this->cli(['--all'])->getExitCode());
        self::assertSame(1, $this->cli(['--all', '--extensions=argument'])->getExitCode());
        self::assertSame(0, $this->cli(['--all', '--extensions=argument', '--ignore-funcs=output'])->getExitCode());
    }

    /** Native zero/one exceptions cannot be removed; user-supplied numbers are additions rather than replacements. */
    public function testNumericExceptionsAreAdditiveAndFinite(): void
    {
        $this->put('source.php', '<?php if ($value === 0 || $value === 1 || $value === 42) {}' . "\n");
        $this->git(['add', '--all']);
        $empty = $this->cli(['--all', '--format=xml', '--ignore-numbers=']);
        self::assertSame(1, $empty->getExitCode(), $empty->getErrorOutput());
        self::assertSame('1', $this->errorCount($this->xml($empty->getOutput())));
        $addition = $this->cli(['--all', '--format=xml', '--ignore-numbers=42']);
        self::assertSame(0, $addition->getExitCode(), $addition->getErrorOutput());
        self::assertSame('0', $this->errorCount($this->xml($addition->getOutput())));
        $this->put('source.php', '<?php if ($value === 42.5) {}' . "\n");
        foreach (['42.5', '4.25e1'] as $number) {
            $addition = $this->cli(['--all', '--format=xml', '--ignore-numbers=' . $number]);
            self::assertSame(0, $addition->getExitCode(), $addition->getErrorOutput());
            self::assertSame('0', $this->errorCount($this->xml($addition->getOutput())));
        }
        $overflow = $this->cli(['--all', '--format=xml', '--ignore-numbers=1e9999']);
        self::assertSame(2, $overflow->getExitCode());
        self::assertSame('', $overflow->getOutput());
        self::assertStringContainsString('finite numeric', $overflow->getErrorOutput());
    }

    /** Invalid XML path/source bytes fail before native streaming and without source mutation or sanitization. */
    public function testXmlRejectsUnsupportedBytesBeforeWritingOutput(): void
    {
        $valid = '<?php if ($value === 42) { echo "first"; }' . "\n";
        $this->put('a-valid.php', $valid);
        $cases = [
            ["path-control\x01.php", $valid, 'selected path'],
            ['source-control.php', '<?php if ($value === 42) { echo "bad' . "\x01" . '"; }', 'selected source'],
            ['source-encoding.php', '<?php if ($value === 42) { echo "bad' . "\xFF" . '"; }', 'selected source'],
        ];
        foreach ($cases as [$path, $source, $diagnostic]) {
            $this->put($path, $source);
            $this->git(['add', '--all']);
            $result = $this->cli(['--all', '--format=xml']);
            self::assertSame(2, $result->getExitCode(), $result->getErrorOutput());
            self::assertSame('', $result->getOutput());
            self::assertStringContainsString($diagnostic, $result->getErrorOutput());
            self::assertSame($source, file_get_contents($this->directory . '/' . $path));
            self::assertSame($valid, file_get_contents($this->directory . '/a-valid.php'));
            (new Filesystem())->remove($this->directory . '/' . $path);
        }
    }

    /** The native DOM reporter splits CDATA terminators without changing the source snippet. */
    public function testXmlRoundTripsCdataTerminator(): void
    {
        $source = '<?php if ($value === 42) { echo "]]><tag>"; }' . "\n";
        $this->put('source.php', $source);
        $this->git(['add', '--all']);
        $result = $this->cli(['--all', '--format=xml']);
        self::assertSame(1, $result->getExitCode(), $result->getErrorOutput());
        $snippet = $this->xml($result->getOutput())->getElementsByTagName('snippet')->item(0);
        self::assertNotNull($snippet);
        self::assertSame(rtrim($source, "\n"), $snippet->textContent);
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
    }

    /** Empty selection is represented by the same native XML structure as a clean selected file. */
    public function testEmptySelectionMatchesNativeCleanDocument(): void
    {
        $this->git(['add', '--all']);
        $empty = $this->cli(['--all', '--format=xml']);
        self::assertSame(0, $empty->getExitCode(), $empty->getErrorOutput());
        $this->xml($empty->getOutput());
        $this->put('clean.php', '<?php if ($value === 0) { echo "clean"; }' . "\n");
        $this->git(['add', '--all']);
        $clean = $this->cli(['--all', '--format=xml']);
        self::assertSame(0, $clean->getExitCode(), $clean->getErrorOutput());
        self::assertSame($empty->getOutput(), $clean->getOutput());
    }

    /** Changed-file analysis preserves exact-head/clean-checkout validation and does not scan unrelated debt. */
    public function testChangedSelectionUsesExactCommits(): void
    {
        $this->put('old.php', '<?php if ($value === 42) {}' . "\n");
        $this->git(['add', '--all']);
        $this->commit();
        $base = trim($this->git(['rev-parse', 'HEAD']));
        $this->put('new.php', '<?php if ($value === 0) {}' . "\n");
        $this->git(['add', '--all']);
        $this->commit();
        $head = trim($this->git(['rev-parse', 'HEAD']));
        $arguments = ['--base=' . $base, '--head=' . $head, '--format=xml'];
        self::assertSame(0, $this->cli($arguments)->getExitCode());
        self::assertSame(1, $this->cli(['--all'])->getExitCode());
        $this->put('new.php', '<?php if ($value === 43) {}' . "\n");
        self::assertSame(2, $this->cli($arguments)->getExitCode());
    }

    /** Invalid input produces failing diagnostics on stderr rather than corrupting machine stdout. */
    public function testInvalidArgumentsAndHelp(): void
    {
        $this->git(['add', '--all']);
        $invalid = [
            [],
            ['--all', '--format=json'],
            ['--all', '--ignore-numbers=not-a-number'],
            ['--all', '--extensions=missing-context'],
            ['--all', '--config=arbitrary.php'],
            ['--all', '--fix'],
        ];
        foreach ($invalid as $arguments) {
            $result = $this->cli($arguments);
            self::assertSame(2, $result->getExitCode(), implode(' ', $arguments));
            self::assertSame('', $result->getOutput());
            self::assertNotSame('', $result->getErrorOutput());
        }
        $help = $this->cli(['--help']);
        self::assertSame(0, $help->getExitCode());
        self::assertStringContainsString('magic-numbers', $help->getOutput());
        self::assertStringContainsString('--ignore-numbers', $help->getOutput());
    }

    /** Analyzer parsing failures are failures, and no source repair is attempted. */
    public function testNativeParseFailureIsNotReportedAsClean(): void
    {
        $source = '<?php if (';
        $this->put('broken.php', $source);
        $this->git(['add', '--all']);
        self::assertNotSame(0, $this->cli(['--all', '--format=xml'])->getExitCode());
        self::assertSame($source, file_get_contents($this->directory . '/broken.php'));
    }

    /**
     * Exercises actual extension availability; a mocked capability callback would not prove this runtime boundary.
     *
     * A real DOM-free subprocess permits the recording text transport but rejects XML before transport or stdout.
     * Hosts with statically available DOM skip honestly rather than simulating its absence in production code.
     */
    public function testDomFreeRuntimeKeepsTextAndRejectsXml(): void
    {
        $probe = new Process([PHP_BINARY, '-n', '-r', 'exit(extension_loaded("dom") ? 1 : 0);'], timeout: 10);
        if (1 === $probe->run()) {
            self::markTestSkipped('DOM remains statically available with php -n on this host; no DOM-free runtime is installed.');
        }
        self::assertSame(0, $probe->getExitCode(), $probe->getErrorOutput());
        $this->put('source.php', '<?php if ($value === 42) {}' . "\n");
        $this->git(['add', '--all']);
        $root = dirname(__DIR__, 2);
        foreach (['text' => [17, '1'], 'xml' => [2, '0']] as $format => [$status, $runs]) {
            $counter = $this->directory . '/runs-' . $format;
            $process = new Process([
                PHP_BINARY, '-n', $root . '/tests/Fixtures/magic-numbers-runtime.php.inc',
                $root . '/vendor/autoload.php', $this->directory, $format, $counter,
            ], $this->directory, timeout: 15);
            self::assertSame($status, $process->run(), $process->getErrorOutput());
            self::assertSame('', $process->getOutput());
            self::assertSame($runs, file_get_contents($counter));
            if ('xml' === $format) {
                self::assertStringContainsString('PHP DOM extension', $process->getErrorOutput());
            } else {
                self::assertSame('', $process->getErrorOutput());
            }
        }
    }

    /** @param list<string> $arguments Explicit command options without shell parsing. */
    private function cli(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'magic-numbers', ...$arguments], $this->directory, timeout: 60);
        $process->run();

        return $process;
    }

    /** Parses only the command's owned XML output, with network entity loading disabled. */
    private function xml(string $output): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($output, LIBXML_NONET));

        return $document;
    }

    /** Requires the native report root before reading its finding count. */
    private function errorCount(DOMDocument $document): string
    {
        $root = $document->documentElement;
        self::assertNotNull($root);

        return $root->getAttribute('errorCount');
    }

    /** Writes only within this test's exclusive synthetic fixture. */
    private function put(string $path, string $source): void
    {
        (new Filesystem())->mkdir(dirname($this->directory . '/' . $path));
        file_put_contents($this->directory . '/' . $path, $source);
    }

    /** @param list<string> $arguments Native Git operations confined to the disposable fixture. */
    private function git(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->directory, timeout: 30);
        $process->mustRun();

        return $process->getOutput();
    }

    /** Commits synthetic files with fixture-local signing/hooks disabled; real repository hooks remain unchanged. */
    private function commit(): void
    {
        $this->git(['-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--quiet', '-m', 'Synthetic fixture']);
    }
}
