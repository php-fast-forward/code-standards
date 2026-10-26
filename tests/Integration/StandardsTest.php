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
        $this->put('.gitignore', "/vendor/\n");
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
        $this->git(['add', '-f', 'vendor/ignored.php']);
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

    /** Checks trailing root-path spaces/control bytes without weakening nested-checkout rejection. */
    public function testCheckoutRootWhitespaceIsPreservedInAllAndChangedModes(): void
    {
        $renamed = $this->directory . " \t\n";
        rename($this->directory, $renamed);
        $this->directory = $renamed;

        $base = $this->commit();
        $source = "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n";
        $this->put('source.php', $source);
        $head = $this->commit();
        $selection = new FileSelection(new NativeProcessRunner());

        self::assertSame(['source.php'], $selection->select($this->directory, null, null));
        self::assertSame(['source.php'], $selection->select($this->directory, $base, $head));
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
    }

    /** Rejects metadata/bare repositories instead of silently checking an empty source selection. */
    public function testGitMetadataAndBareRepositoriesAreNotSourceCheckoutRoots(): void
    {
        $this->git(['init', '--bare', '--initial-branch=main', 'bare.git']);

        foreach (['.git', 'bare.git'] as $directory) {
            $check = $this->cli(['check', '--all', '--directory=' . $this->directory . '/' . $directory]);

            self::assertSame(2, $check->getExitCode(), $directory);
            self::assertStringContainsString('Git checkout root', $check->getErrorOutput(), $directory);
        }
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

        foreach (['style', 'preservation', 'documentation'] as $name) {
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

    /** ECS already loads the installation autoloader; a symlinked preset needs no vendor beside its real source. */
    public function testPresetCanLoadThroughSymlinkWithoutAnAdjacentVendor(): void
    {
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n");
        $this->put('package-source/config/ecs.php', file_get_contents(dirname(__DIR__, 2) . '/config/ecs.php'));
        mkdir($this->directory . '/linked');
        symlink('../package-source', $this->directory . '/linked/package');
        $this->commit();

        foreach (['check', 'fix'] as $command) {
            $result = $this->cli([$command, '--all', '--config=linked/package/config/ecs.php']);
            self::assertSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        }
        self::assertFileDoesNotExist($this->directory . '/package-source/vendor');
        self::assertSame("<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n", file_get_contents($this->directory . '/source.php'));
    }

    /** Runs the real default preset across two checkouts without erasing the first checkout's cache. */
    public function testDefaultCachesDoNotInvalidateAnotherCheckout(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\nreturn 1;\n";
        $this->put('source.php', $source);
        $this->commit();
        $this->put('.build/project-b/source.php', $source);
        $second = $this->directory . '/.build/project-b';
        $temporary = $this->directory . '/.build/private-tmp';
        mkdir($temporary);
        $this->git(['init', '-b', 'main', $second]);
        $this->git(['-C', $second, 'add', 'source.php']);
        $environment = ['TMPDIR' => $temporary];
        $cache = $temporary . '/fast-forward-code-standards/' . hash('sha256', $this->directory);

        $first = $this->cli(['check', '--all'], $environment);
        self::assertSame(0, $first->getExitCode(), $first->getOutput() . $first->getErrorOutput());
        self::assertDirectoryExists($cache);
        file_put_contents($cache . '/owned-marker', 'project A cache');

        foreach (['check', 'fix', 'check', 'fix'] as $command) {
            $result = $this->cli([$command, '--all', '--directory=' . $second], $environment);

            self::assertSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
            self::assertSame('project A cache', file_get_contents($cache . '/owned-marker'));
            self::assertSame($source, file_get_contents($second . '/source.php'));
            self::assertSame($source, file_get_contents($this->directory . '/source.php'));
            self::assertFileDoesNotExist($second . '/composer.lock');
        }

        $this->put('.build/explicit-ecs.php', '<?php return \\FastForward\\CodeStandards\\Config\\ECSConfig::configure('
            . 'static function (\\Symplify\\EasyCodingStandard\\Configuration\\ECSConfigBuilder $config): void {'
            . '$config->withCache(__DIR__ . "/explicit-cache", "consumer");});');
        $override = $this->cli(['check', '--all', '--config=.build/explicit-ecs.php'], $environment);

        self::assertSame(0, $override->getExitCode(), $override->getOutput() . $override->getErrorOutput());
        self::assertDirectoryExists($this->directory . '/.build/explicit-cache');
        self::assertSame('project A cache', file_get_contents($cache . '/owned-marker'));
    }

    /** A successful explicit fix must not hide a remaining violation after an EOF-sensitive formatter pass. */
    public function testSuccessfulFixCannotHideRemainingStyleViolations(): void
    {
        $this->put('source.php', '<?php echo 1;');
        $this->commit();
        $fix = $this->cli(['fix', '--all']);
        $after = file_get_contents($this->directory . '/source.php');
        $check = $this->cli(['check', '--all']);

        self::assertNotSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($check->getExitCode(), $fix->getExitCode(), $fix->getOutput() . $check->getOutput());
        self::assertSame($after, file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        $clean = file_get_contents($this->directory . '/source.php');

        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($clean, file_get_contents($this->directory . '/source.php'));
    }

    /** Applies arbitrary consumer patterns to tracked files, preserving Git negation, escape and parent-directory semantics. */
    public function testConsumerGitIgnoresFilterTrackedFilesDynamically(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n";
        $ignored = [
            'root-only.php', 'deep/scratch-one.php', 'bundle-42/data.php',
            '#tag.php', '!bang.php', 'space name.php', "line\nbreak.php",
            'module/drop.php', 'blocked-parent/keep.php',
        ];
        $eligible = [
            'source.php', 'deep/root-only.php', 'module/keep.php',
            'public/source.php', 'resources/source.php', 'backup/source.php',
            'global.php', 'info.php',
        ];
        $this->put('.gitignore', "# Consumer-owned rules\n/root-only.php\n**/scratch-*.php\nbundle-*/\n\\#tag.php\n\\!bang.php\nspace\\ name.php\nline?break.php\nblocked-parent/\n");
        $this->put('module/.gitignore', "*.php\n!keep.php\n");
        $this->put('blocked-parent/.gitignore', "!keep.php\n");
        foreach ([...$ignored, ...$eligible] as $path) {
            $this->put($path, $source);
        }
        $this->put('.build/global-ignore', "global.php\n");
        $this->git(['config', 'core.excludesFile', $this->directory . '/.build/global-ignore']);
        file_put_contents($this->directory . '/.git/info/exclude', "info.php\n");
        $this->git(['add', '-f', '--all']);
        $base = $this->commit();
        $selection = new FileSelection(new NativeProcessRunner());
        $selected = (new PhpFiles())->filter($this->directory, $selection->select($this->directory, null, null));
        $expected = array_map(fn (string $path): string => $this->directory . '/' . $path, $eligible);
        sort($expected, SORT_STRING);
        self::assertSame($expected, $selected);

        $this->put('root-only.php', str_replace("'ok'", "'ignored edit'", $source));
        $this->put('source.php', str_replace("'ok'", "'visible edit'", $source));
        $head = $this->commit();
        self::assertSame(['source.php'], $selection->select($this->directory, $base, $head));
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertStringContainsString('blocked-parent/', file_get_contents($this->directory . '/.gitignore'));
    }

    /** Root and nested ignore changes/removals rescan unchanged eligible tracked code. */
    public function testChangedIgnorePoliciesInvalidateTheSelectedScope(): void
    {
        $this->put('unchanged.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n");
        $selection = new FileSelection(new NativeProcessRunner());

        foreach (['.gitignore', 'nested/.gitignore'] as $policy) {
            foreach (['modify', 'delete'] as $operation) {
                $ignored = dirname($policy) . '/blocked.php';
                $ignored = str_starts_with($ignored, './') ? substr($ignored, 2) : $ignored;
                $this->put($policy, "blocked.php\n");
                $this->put($ignored, '<?php echo 1;');
                $this->git(['add', '-f', $ignored]);
                $base = $this->commit();
                self::assertNotContains($ignored, $selection->select($this->directory, null, null));

                if ('delete' === $operation) {
                    $this->git(['rm', $policy]);
                } else {
                    $this->put($policy, "different.php\n");
                }
                $head = $this->commit();
                $selected = $selection->select($this->directory, $base, $head);
                self::assertContains('unchanged.php', $selected, $policy . ':' . $operation);
                self::assertContains($ignored, $selected, $policy . ':' . $operation);
            }
        }
    }

    /** A missing Git executable fails explicitly; no parser fallback silently changes the consumer scope. */
    public function testMissingGitFailsInsteadOfIgnoringConsumerRules(): void
    {
        $result = $this->cli(['check', '--all'], ['PATH' => $this->directory . '/no-executables']);

        self::assertSame(2, $result->getExitCode());
        self::assertStringContainsString('git', strtolower($result->getErrorOutput()));
    }

    /** Linked worktrees use a .git file; a non-Git project still fails explicitly rather than silently passing. */
    public function testLinkedWorktreeMetadataFileAndNonGitProjectScope(): void
    {
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ok';\n");
        $this->commit();
        $linked = $this->directory . '/.build/linked';
        $this->git(['worktree', 'add', '--detach', $linked, 'HEAD']);

        self::assertFileExists($linked . '/.git');
        self::assertFalse(is_dir($linked . '/.git'));
        self::assertSame(0, $this->cli(['check', '--all', '--directory=' . $linked])->getExitCode());

        $plain = $this->directory . '-plain';
        mkdir($plain);
        file_put_contents($plain . '/source.php', '<?php broken source');

        try {
            $identity = new Process(['git', 'rev-parse', '--is-inside-work-tree'], $plain);
            $identity->run();
            self::assertFalse($identity->isSuccessful(), 'The fixture must actually be outside Git.');

            $result = $this->cli(['check', '--all', '--directory=' . $plain]);
            self::assertSame(2, $result->getExitCode());
            self::assertNotSame('', $result->getErrorOutput());
        } finally {
            unlink($plain . '/source.php');
            rmdir($plain);
        }
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

    /**
     * Runs the real packaged binary in the fixture with bounded timeout and captured diagnostics.
     *
     * @param list<string> $arguments Explicit CLI arguments.
     * @param array<string, string> $environment Invocation-local overrides, including isolated test temp directories.
     */
    private function cli(array $arguments, array $environment = []): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', ...$arguments], $this->directory, $environment, timeout: 60);
        $process->run();

        return $process;
    }

    /** Lists both tracked files and unexpected new files to detect incidental check writes. */
    private function trackedAndUntrackedSources(): array
    {
        return array_values(array_filter(explode("\0", $this->git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']))));
    }
}
