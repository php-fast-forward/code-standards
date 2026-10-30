<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises the actual internal worker against owned consumer repositories and trusted native config. */
final class EcsBootstrapTest extends TestCase
{
    private string $directory;

    /** Creates synthetic source and metadata without installing dependencies or executing application code. */
    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-bootstrap-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        mkdir($this->directory . '/.cache');
        $this->put('.gitignore', "/vendor/\n/.build/\n/.cache/\n");
        $this->put('composer.json', '{"name":"fixture/consumer"}' . "\n");
        $this->put('composer.lock', '{"packages":[]}' . "\n");
        $this->git(['init', '-q']);
    }

    /** Removes only this test's exclusive disposable files, without following symlinks. */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** @return iterable<string, array{bool}> Both Git tracking policies must leave implicit bootstrap disabled. */
    public static function autoloadTracking(): iterable
    {
        yield 'ignored' => [false];
        yield 'tracked' => [true];
    }

    /** Keeps ignored/tracked application autoload inert through check, fix and verification with binary-safe arguments. */
    #[DataProvider('autoloadTracking')]
    public function testDefaultCheckAndFixDoNotImportConsumerAutoload(bool $tracked): void
    {
        $paths = ['src/a file.php', "src/tab\tline\nfile.php", 'src/-leading.php'];
        $bad = "<?php\n\ndeclare(strict_types=1);\n\nfile_put_contents(__DIR__ . '/source-executed', 'unexpected');\n\$value=1;\n";
        foreach ($paths as $path) {
            $this->put($path, $bad);
        }
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../executed", "unexpected");' . "\n");
        $this->put('ecs.php', '<?php file_put_contents(__DIR__ . "/config-executed", "unexpected"); throw new RuntimeException("Implicit config executed");' . "\n");
        $this->git(['add', '--all']);
        if ($tracked) {
            $this->git(['add', '-f', '--', 'vendor/autoload.php']);
        }
        $lock = hash_file('sha256', $this->directory . '/composer.lock');
        $autoload = hash_file('sha256', $this->directory . '/vendor/autoload.php');
        $check = $this->cli(['check', '--all']);
        self::assertSame(2, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        foreach ($paths as $path) {
            self::assertSame($bad, file_get_contents($this->directory . '/' . $path));
        }
        $fix = $this->cli(['fix', '--all']);
        self::assertSame(0, $fix->getExitCode(), $fix->getOutput() . $fix->getErrorOutput());
        $fixed = array_map(fn(string $path): string => file_get_contents($this->directory . '/' . $path), $paths);
        self::assertNotSame($bad, $fixed[0]);
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($fixed, array_map(fn(string $path): string => file_get_contents($this->directory . '/' . $path), $paths));
        self::assertFileDoesNotExist($this->directory . '/executed');
        self::assertFileDoesNotExist($this->directory . '/config-executed');
        self::assertFileDoesNotExist($this->directory . '/src/source-executed');
        self::assertSame($lock, hash_file('sha256', $this->directory . '/composer.lock'));
        self::assertSame($autoload, hash_file('sha256', $this->directory . '/vendor/autoload.php'));
    }

    /** Explicit config can opt into its own checker/autoload without changing cwd or trusted engine resolution. */
    public function testExplicitConsumerCheckerAndContextRemainSupported(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\necho 'needs-fix';\n";
        $this->put('source.php', $source);
        $this->put('relative-marker', 'owned marker');
        $this->put('.editorconfig', "root = true\n");
        $this->put('vendor/BootstrapFixer.php', file_get_contents(__DIR__ . '/../Fixtures/BootstrapFixer.php.inc'));
        $this->put('vendor/composer/installed.php', '<?php return ' . var_export([
            'root' => ['name' => 'fixture/consumer'],
            'versions' => ['symplify/easy-coding-standard' => ['install_path' => $this->directory . '/.build/nonexistent-engine']],
        ], true) . ';');
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../autoload-opt-in", "explicit"); '
            . '$loader = new Composer\\Autoload\\ClassLoader(__DIR__); $loader->register(true); '
            . 'require_once __DIR__ . "/BootstrapFixer.php";' . "\n");
        $this->put('.build/explicit.php', file_get_contents(__DIR__ . '/../Fixtures/explicit-bootstrap-config.php.inc'));
        $this->git(['add', '--all']);
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertFileDoesNotExist($this->directory . '/autoload-opt-in');
        $check = $this->cli(['check', '--all', '--config=.build/explicit.php']);
        self::assertSame(2, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        self::assertSame('explicit', file_get_contents($this->directory . '/autoload-opt-in'));
        $context = json_decode(file_get_contents($this->directory . '/.build/context.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($this->directory, $context['cwd']);
        self::assertSame($this->directory . '/.build', $context['config_directory']);
        self::assertTrue($context['relative']);
        self::assertTrue($context['absolute']);
        self::assertTrue($context['editorconfig']);
        self::assertTrue($context['metadata_changed']);
        self::assertSame('fixture/consumer', $context['package']);
        self::assertStringStartsWith(realpath(dirname(__DIR__, 2) . '/vendor/symplify/easy-coding-standard') . '/', $context['native_source']);
        $fix = $this->cli(['fix', '--all', '--config=.build/explicit.php']);
        self::assertSame(0, $fix->getExitCode(), $fix->getOutput() . $fix->getErrorOutput());
        self::assertStringContainsString("echo 'fixed';", file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['check', '--all', '--config=.build/explicit.php'])->getExitCode());
    }

    /** Native config invalidation remains effective and factory failures retain a failing formatter status. */
    public function testConfigChangesInvalidateCacheAndErrorsFailClosed(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\necho \"ready\";\n";
        $this->put('source.php', $source);
        $this->put('.build/policy.php', '<?php return FastForward\\CodeStandards\\Config\\ECSConfig::configure();');
        $this->git(['add', '--all']);
        self::assertSame(0, $this->cli(['check', '--all', '--config=.build/policy.php'])->getExitCode());
        $this->put('.build/policy.php', '<?php return FastForward\\CodeStandards\\Config\\ECSConfig::configure('
            . 'static fn($config) => $config->withRules([PhpCsFixer\\Fixer\\StringNotation\\SingleQuoteFixer::class]));');
        self::assertSame(2, $this->cli(['check', '--all', '--config=.build/policy.php'])->getExitCode());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        foreach (['<?php throw new RuntimeException("fixture config failure");', '<?php return [];', '<?php return ['] as $invalid) {
            $this->put('.build/policy.php', $invalid);
            $result = $this->cli(['check', '--all', '--config=.build/policy.php']);
            self::assertSame(1, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
            self::assertStringContainsString('ECS worker:', $result->getErrorOutput());
            self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        }
        self::assertSame(2, $this->cli(['check', '--all', '--config=missing.php'])->getExitCode());
    }

    /** The source worker needs only its supplied runtime; the PHAR harness tests archive execution. */
    public function testSourceWorkerUsesOnlyItsSuppliedRuntime(): void
    {
        $root = dirname(__DIR__, 2);
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ready';\n");
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../executed", "unexpected");');
        $this->git(['add', '--all']);
        $worker = $this->worker([
            $root . '/config/ecs.php', '--check', '--', $this->directory . '/source.php',
        ]);
        self::assertSame(0, $worker->getExitCode(), $worker->getOutput() . $worker->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/executed');
    }

    /** Rejects unknown internal modes without interpreting them as write authority. */
    public function testWorkerRejectsMalformedProtocol(): void
    {
        $this->put('source.php', "<?php\n");
        $result = $this->worker([dirname(__DIR__, 2) . '/config/ecs.php', '--unknown', '--', $this->directory . '/source.php']);
        self::assertSame(1, $result->getExitCode());
        self::assertStringContainsString('explicit mode', $result->getErrorOutput());
    }

    /** Composer's normal binary-proxy protocol retains the chosen installation's intentional bootstrap. */
    public function testComposerBinaryProxyKeepsItsOwnInstallationAutoloader(): void
    {
        $root = dirname(__DIR__, 2);
        $this->put('source.php', "<?php\n\ndeclare(strict_types=1);\n\necho 'ready';\n");
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../own-bootstrap", "intentional"); require '
            . var_export($root . '/vendor/autoload.php', true) . ';');
        (new Filesystem())->mkdir($this->directory . '/vendor/symplify');
        symlink(realpath($root . '/vendor/symplify/easy-coding-standard'), $this->directory . '/vendor/symplify/easy-coding-standard');
        $this->put('vendor/bin/code-standards', '<?php $_composer_autoload_path = __DIR__ . "/../autoload.php"; require '
            . var_export($root . '/bin/code-standards', true) . ';');
        $this->git(['add', '--all']);

        $process = $this->process([PHP_BINARY, $this->directory . '/vendor/bin/code-standards', 'check', '--all']);

        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertSame('intentional', file_get_contents($this->directory . '/own-bootstrap'));
        self::assertSame("<?php\n\ndeclare(strict_types=1);\n\necho 'ready';\n", file_get_contents($this->directory . '/source.php'));
    }

    /** @return iterable<string, array{string}> Supported direct-entrypoint layouts. */
    public static function installationLayouts(): iterable
    {
        yield 'checkout' => ['.build/checkout'];
        yield 'installed package called directly by CPX' => ['.build/runtime/vendor/fast-forward/code-standards'];
    }

    /** The entrypoint resolves its installation even when cwd contains an unrelated autoloader. */
    #[DataProvider('installationLayouts')]
    public function testDirectEntrypointUsesInstallationAutoloader(string $package): void
    {
        $root = dirname(__DIR__, 2);
        $vendor = '.build/checkout' === $package ? $package . '/vendor' : '.build/runtime/vendor';
        $this->put($package . '/bin/code-standards', file_get_contents($root . '/bin/code-standards'));
        $this->put($vendor . '/autoload.php', '<?php file_put_contents(__DIR__ . "/loaded", "installation"); require '
            . var_export($root . '/vendor/autoload.php', true) . ';');
        (new Filesystem())->mkdir($this->directory . '/' . $vendor . '/symplify');
        symlink(realpath($root . '/vendor/symplify/easy-coding-standard'), $this->directory . '/' . $vendor . '/symplify/easy-coding-standard');
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../executed", "unexpected");');

        $process = $this->process([PHP_BINARY, $this->directory . '/' . $package . '/bin/code-standards', 'list']);

        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringContainsString('normalize-composer', $process->getOutput());
        self::assertSame('', $process->getErrorOutput());
        self::assertSame('installation', file_get_contents($this->directory . '/' . $vendor . '/loaded'));
        self::assertFileDoesNotExist($this->directory . '/executed');
    }

    /** Missing dependencies produce one actionable stderr message without importing cwd PHP. */
    public function testMissingInstallationAutoloaderFailsCleanly(): void
    {
        $this->put('.build/missing/package/bin/code-standards', file_get_contents(dirname(__DIR__, 2) . '/bin/code-standards'));
        $this->put('vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../executed", "unexpected");');

        $process = $this->process([PHP_BINARY, $this->directory . '/.build/missing/package/bin/code-standards', 'list']);

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertSame("Could not locate Composer autoload.php for fast-forward/code-standards.\n", $process->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/executed');
    }

    /** @param list<string> $arguments User-visible command arguments. */
    private function cli(array $arguments): Process
    {
        return $this->process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', ...$arguments]);
    }

    /** @param list<string> $arguments Internal config/mode/separator/path fields after the trusted installation. */
    private function worker(array $arguments, ?string $entrypoint = null): Process
    {
        $root = dirname(__DIR__, 2);

        return $this->process([PHP_BINARY, $entrypoint ?? $root . '/bin/ecs-worker', $root . '/vendor/autoload.php', realpath($root . '/vendor/symplify/easy-coding-standard'), ...$arguments]);
    }

    /**
     * Runs one real process with an exclusive temporary cache and a deliberately fake token.
     *
     * @param list<string> $command Executable and separate arguments, without shell expansion.
     */
    private function process(array $command): Process
    {
        $process = new Process($command, $this->directory, ['TMPDIR' => $this->directory . '/.cache', 'GH_TOKEN' => 'fixture-only-not-a-credential'], timeout: 60);
        $process->run();

        return $process;
    }

    /** Writes synthetic content only beneath the exclusively owned fixture. */
    private function put(string $path, string $contents): void
    {
        (new Filesystem())->mkdir(dirname($this->directory . '/' . $path));
        file_put_contents($this->directory . '/' . $path, $contents);
    }

    /** @param list<string> $arguments Read/write Git operations restricted to the synthetic fixture. */
    private function git(array $arguments): void
    {
        $process = new Process(['git', ...$arguments], $this->directory, timeout: 30);
        $process->mustRun();
    }
}
