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

    /** Creates a disposable repository with synthetic author and no inherited signing requirement. */
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

    /** Removes only this test's disposable tree, unlinking symlinks without following them. */
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

    /** Exercises divergence, rename destinations, deleted sources and binary-safe path arguments. */
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

    /** Ensures removing a policy file cannot limit checking to an empty changed-file set. */
    public function testDeletedConfigurationStillTriggersFullScan(): void
    {
        $this->put('unchanged.php', '<?php echo 1;');
        $this->put('ecs.php', '<?php return null;');
        $base = $this->commit();
        $this->git(['rm', 'ecs.php']);
        $head = $this->commit();
        self::assertSame(['unchanged.php'], (new FileSelection(new NativeProcessRunner()))->select($this->directory, $base, $head));
    }

    /** Prevents checks from reporting on a different commit or dirty tracked source snapshot. */
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

    /** Uses real ECS to prove violation status, source preservation and second-pass fix stability. */
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

    /** Proves parsing never executes selected source and syntax failures leave its bytes intact. */
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

    /** Allows documentation-only PRs to complete without invoking the style tool. */
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

    /** Preserves complete policy-change checks and rejects ambiguous nested-project Git paths. */
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

    /** Keeps PHP symlinks and parent-directory paths outside the permitted source boundary. */
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

    /** Checks that documentation aliases cannot block PHP checks or admit executable symlinks. */
    public function testNonPhpSymlinksAreIgnoredButBinSymlinksAreRejected(): void
    {
        $this->put('AGENTS.md', 'Repository instructions.');
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n");
        symlink('AGENTS.md', $this->directory . '/CLAUDE.md');
        $this->commit();
        $check = $this->cli(['check', '--all']);
        self::assertSame(0, $check->getExitCode(), $check->getErrorOutput());
        self::assertStringContainsString('Selected 1 PHP', $check->getOutput());
        mkdir($this->directory . '/bin');
        symlink('../source.php', $this->directory . '/bin/tool');
        $this->expectException(\InvalidArgumentException::class);
        (new PhpFiles())->filter($this->directory, ['bin/tool']);
    }

    /** Checks invalid invocations, unavailable history and executable config failures return errors. */
    public function testMalformedArgumentsConfigurationAndHistoryFail(): void
    {
        foreach ([
            ['check'],
            ['check', '--all', '--head=' . str_repeat('a', 40)],
            ['check', '--base=main', '--head=' . str_repeat('a', 40)],
            ['check', '--base=' . str_repeat('a', 40), '--head=' . str_repeat('a', 40)],
            ['check', '--all', '--fix'],
            ['check', '--all', '--config=missing.php'],
            ['check', '--all', '--directory='],
            ['check', '--all', '--config='],
            ['check', '--all', '--unknown'],
            ['check', '--all', 'unexpected'],
            ['check', '--all', '--config'],
            ['unknown'],
        ] as $arguments) {
            self::assertSame(2, $this->cli($arguments)->getExitCode(), json_encode($arguments));
        }
        $this->put('a.php', '<?php echo 1;');
        $this->put('ecs.php', '<?php throw new RuntimeException("broken config");');
        $this->commit();
        self::assertNotSame(0, $this->cli(['check', '--all', '--config=ecs.php'])->getExitCode());

        $this->put('ecs.php', '<?php return [];');
        self::assertNotSame(0, $this->cli(['check', '--all', '--config=ecs.php'])->getExitCode());
    }

    /** Native help needs no checkout, and standard option forms share the same checked behavior. */
    public function testNativeConsoleHelpAndSeparateOptionValues(): void
    {
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n");
        $this->commit();
        $check = $this->cli(['check', '--all', '--directory', $this->directory, '--no-interaction']);
        self::assertSame(0, $check->getExitCode(), $check->getErrorOutput());
        self::assertStringContainsString('Selected 1 PHP', $check->getOutput());

        $help = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'help', 'check'], sys_get_temp_dir(), timeout: 10);
        self::assertSame(0, $help->run());
        self::assertStringContainsString('Usage:', $help->getOutput());
        self::assertStringContainsString('--base', $help->getOutput());
    }

    /** A real native-builder override is applied last without replacing the default preset or source bytes. */
    public function testExplicitPresetOverrideUsesNativeEcsConfiguration(): void
    {
        $bad = "<?php\n\ndeclare(strict_types=1);\n\n\$value=1;\n";
        $this->put('source.php', $bad);
        $this->commit();
        self::assertNotSame(0, $this->cli(['check', '--all'])->getExitCode());

        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $this->put('local-policy.php', '<?php require ' . $autoload . '; return \\FastForward\\CodeStandards\\Config\\ECSConfig::configure('
            . 'static function (\\Symplify\\EasyCodingStandard\\Configuration\\ECSConfigBuilder $config): void {'
            . '$config->withSkip([\\PhpCsFixer\\Fixer\\Operator\\BinaryOperatorSpacesFixer::class]); });');
        $check = $this->cli(['check', '--all', '--config', 'local-policy.php']);
        self::assertSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($bad, file_get_contents($this->directory . '/source.php'));
    }

    /** Golden files expose style drift, preserved public documentation and explicit second-pass stability. */
    public function testGoldenPresetIsReadOnlyUntilFixAndPreservesContracts(): void
    {
        $fixtures = dirname(__DIR__) . '/Fixtures/';
        $original = [];

        foreach (['style', 'preservation'] as $name) {
            $original[$name] = file_get_contents($fixtures . $name . '.before.php.inc');
            $this->put($name . '.php', $original[$name]);
        }

        $this->commit();
        self::assertNotSame(0, $this->cli(['check', '--all'])->getExitCode());
        foreach ($original as $name => $bytes) {
            self::assertSame($bytes, file_get_contents($this->directory . '/' . $name . '.php'));
        }

        $fixed = $this->cli(['fix', '--all']);
        self::assertSame(0, $fixed->getExitCode(), $fixed->getOutput() . $fixed->getErrorOutput());
        foreach (array_keys($original) as $name) {
            self::assertSame(
                file_get_contents($fixtures . $name . '.after.php.inc'),
                file_get_contents($this->directory . '/' . $name . '.php'),
                $name,
            );
        }

        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        foreach (array_keys($original) as $name) {
            self::assertSame(file_get_contents($fixtures . $name . '.after.php.inc'), file_get_contents($this->directory . '/' . $name . '.php'));
        }
    }

    /** Both formatter modes retain the owner's spacing between attributed command parameter groups. */
    public function testFormatterPreservesAttributedParameterSpacing(): void
    {
        $source = <<<'PHP'
<?php

declare(strict_types=1);

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;

#[AsCommand(
    name: 'example',
    description: 'Formatting fixture; never executed.',
)]
final class ExampleCommand
{
    public function __invoke(
        #[Option(description: 'All tracked files.')]
        bool $all = false,

        #[Option(description: 'Immutable base commit.')]
        ?string $base = null,
    ): int {
        return 0;
    }
}
PHP;
        $source .= "\n";
        $this->put('example.php', $source);
        $this->commit();

        $check = $this->cli(['check', '--all']);
        self::assertSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($source, file_get_contents($this->directory . '/example.php'));
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($source, file_get_contents($this->directory . '/example.php'));
    }

    /** Writes a synthetic fixture beneath the disposable test root, creating its parents. */
    private function put(string $path, string $content): void
    {
        $parent = dirname($this->directory . '/' . $path);
        if (!is_dir($parent)) {
            mkdir($parent, recursive: true);
        }
        file_put_contents($this->directory . '/' . $path, $content);
    }

    /** Captures Git fixture output through the production shell-free transport. */
    private function git(array $arguments): string
    {
        return (new NativeProcessRunner())->capture(['git', ...$arguments], $this->directory);
    }

    /** Commits fixture changes without hooks and returns the exact resulting commit identity. */
    private function commit(): string
    {
        $this->git(['add', '--all']);
        $this->git(['-c', 'core.hooksPath=/dev/null', 'commit', '--allow-empty', '-m', 'fixture']);

        return trim($this->git(['rev-parse', 'HEAD']));
    }

    /** Runs the real packaged binary in the fixture with a bounded timeout and captured diagnostics. */
    private function cli(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', ...$arguments], $this->directory, timeout: 60);
        $process->run();

        return $process;
    }

    /** Lists both tracked files and unexpected new files to detect incidental check writes. */
    private function trackedAndUntrackedSources(): array
    {
        return array_values(array_filter(explode("\0", $this->git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']))));
    }
}
