<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Exercises real disposable Git repositories and the actual packaged tools. */
final class StandardsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->git(['init', '-b', 'main']);
        $this->git(['config', 'user.name', 'Fixture']);
        $this->git(['config', 'user.email', 'fixture@example.invalid']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        $this->commit();
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testChangedFilesUseMergeBaseDestinationsAndNulSafePaths(): void
    {
        $this->put('old name.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'old';\n");
        $this->put('deleted.php', '<?php echo 1;');
        $this->put('unchanged.php', '<?php echo 1;');
        $base = $this->commit();
        $this->git(['switch', '-c', 'feature']);
        $this->git(['mv', 'old name.php', "renamed\tfile.php"]);
        $this->git(['rm', 'deleted.php']);
        $this->put("new\nfile.php", '<?php echo 2;');
        $this->put('-leading.php', '<?php echo 2;');
        $this->put('bin/a command', "#!/usr/bin/env php\n<?php\necho 2;\n");
        $this->put('vendor/ignored.php', '<?php echo 2;');
        $head = $this->commit();
        $this->git(['switch', 'main']);
        $this->put('base only.php', '<?php echo 3;');
        $advancedBase = $this->commit();
        $this->git(['switch', 'feature']);
        $paths = (new FileSelection(new NativeProcessRunner()))->select($this->directory, $advancedBase, $head);
        self::assertContains("renamed\tfile.php", $paths);
        self::assertContains("new\nfile.php", $paths);
        self::assertNotContains('deleted.php', $paths);
        self::assertNotContains('old name.php', $paths);
        self::assertNotContains('base only.php', $paths);
        self::assertNotSame($base, $advancedBase);
        $filtered = (new PhpFiles())->filter($this->directory, $paths);
        self::assertCount(4, $filtered);
        self::assertContains($this->directory . '/-leading.php', $filtered);
    }

    public function testDeletedConfigurationStillTriggersFullScan(): void
    {
        $this->put('unchanged.php', '<?php echo 1;');
        $this->put('ecs.php', '<?php return null;');
        $base = $this->commit();
        $this->git(['rm', 'ecs.php']);
        $head = $this->commit();
        self::assertSame(['unchanged.php'], (new FileSelection(new NativeProcessRunner()))->select($this->directory, $base, $head));
    }

    public function testInvalidHeadAndDirtyCheckoutFailClosed(): void
    {
        $this->put('a.php', '<?php echo 1;');
        $base = $this->commit();
        $this->put('a.php', '<?php echo 2;');
        $head = $this->commit();
        $mismatch = $this->cli(['check', '--base=' . $base, '--head=' . $base]);
        self::assertSame(2, $mismatch->getExitCode());
        self::assertStringContainsString('HEAD differs', $mismatch->getErrorOutput());
        $this->put('a.php', '<?php echo 3;');
        $dirty = $this->cli(['check', '--base=' . $base, '--head=' . $head]);
        self::assertSame(2, $dirty->getExitCode());
        self::assertStringContainsString('clean tracked checkout', $dirty->getErrorOutput());
    }

    public function testStyleCheckDoesNotWriteAndExplicitFixIsIdempotent(): void
    {
        $path = "some source.php";
        $bad = "<?php\n\ndeclare(strict_types=1);\n\n\$foo=1;\n";
        $this->put($path, $bad);
        $this->commit();
        $check = $this->cli(['check', '--all']);
        self::assertNotSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($bad, file_get_contents($this->directory . '/' . $path));
        self::assertSame([$path], $this->trackedAndUntrackedSources());
        $fix = $this->cli(['fix', '--all']);
        self::assertSame(0, $fix->getExitCode(), $fix->getOutput() . $fix->getErrorOutput());
        $fixed = file_get_contents($this->directory . '/' . $path);
        self::assertNotSame($bad, $fixed);
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($fixed, file_get_contents($this->directory . '/' . $path));
    }

    public function testSyntaxIsParsedWithoutExecutingSource(): void
    {
        $this->put('a.php', "<?php\nfile_put_contents(__DIR__ . '/executed', 'bad');\n");
        $this->commit();
        $this->cli(['check', '--all']);
        self::assertFileDoesNotExist($this->directory . '/executed');
        $this->put('a.php', '<?php function broken( {');
        $bad = file_get_contents($this->directory . '/a.php');
        self::assertNotSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame($bad, file_get_contents($this->directory . '/a.php'));
    }

    public function testEmptyPhpSelectionSucceedsWithoutEcs(): void
    {
        $this->put('README.md', 'No PHP');
        $base = $this->commit();
        $this->put('README.md', 'Still no PHP');
        $head = $this->commit();
        $check = $this->cli(['check', '--base=' . $base, '--head=' . $head]);
        self::assertSame(0, $check->getExitCode());
        self::assertStringContainsString('Selected 0 PHP', $check->getOutput());
    }

    public function testExplicitCustomConfigChangesTriggerFullScanAndSubdirectoryIsRejected(): void
    {
        $this->put('unchanged.php', '<?php echo 1;');
        $this->put('style-policy.php', '<?php return null;');
        $base = $this->commit();
        $this->put('style-policy.php', '<?php return false;');
        $head = $this->commit();
        $paths = (new FileSelection(new NativeProcessRunner()))->select(
            $this->directory,
            $base,
            $head,
            $this->directory . '/style-policy.php',
        );
        self::assertContains('unchanged.php', $paths);
        mkdir($this->directory . '/nested');
        $check = $this->cli(['check', '--all', '--directory=' . $this->directory . '/nested']);
        self::assertSame(2, $check->getExitCode());
        self::assertStringContainsString('Git checkout root', $check->getErrorOutput());
    }

    public function testSymlinksAndTraversalAreRejected(): void
    {
        $this->put('a.php', '<?php echo 1;');
        symlink($this->directory . '/a.php', $this->directory . '/linked.php');
        try {
            (new PhpFiles())->filter($this->directory, ['linked.php']);
            self::fail('Symlink should be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Symlink', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        (new PhpFiles())->filter($this->directory, ['../a.php']);
    }

    public function testMalformedArgumentsConfigurationAndHistoryFail(): void
    {
        foreach ([
            ['check'],
            ['check', '--all', '--head=' . str_repeat('a', 40)],
            ['check', '--base=main', '--head=' . str_repeat('a', 40)],
            ['check', '--base=' . str_repeat('a', 40), '--head=' . str_repeat('a', 40)],
            ['check', '--all', '--all'],
            ['check', '--all', '--fix'],
            ['check', '--all', '--config=missing.php'],
            ['unknown'],
        ] as $arguments) {
            self::assertSame(2, $this->cli($arguments)->getExitCode(), json_encode($arguments));
        }
        $this->put('a.php', '<?php echo 1;');
        $this->put('ecs.php', '<?php throw new RuntimeException("broken config");');
        $this->commit();
        self::assertNotSame(0, $this->cli(['check', '--all', '--config=ecs.php'])->getExitCode());
    }

    private function put(string $path, string $content): void
    {
        $parent = dirname($this->directory . '/' . $path);
        if (!is_dir($parent)) {
            mkdir($parent, recursive: true);
        }
        file_put_contents($this->directory . '/' . $path, $content);
    }

    private function git(array $arguments): string
    {
        return (new NativeProcessRunner())->capture(['git', ...$arguments], $this->directory);
    }

    private function commit(): string
    {
        $this->git(['add', '--all']);
        $this->git(['-c', 'core.hooksPath=/dev/null', 'commit', '--allow-empty', '-m', 'fixture']);

        return trim($this->git(['rev-parse', 'HEAD']));
    }

    private function cli(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', ...$arguments], $this->directory, timeout: 60);
        $process->run();

        return $process;
    }

    private function trackedAndUntrackedSources(): array
    {
        return array_values(array_filter(explode("\0", $this->git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']))));
    }
}
