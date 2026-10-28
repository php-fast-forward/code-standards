<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use Composer\Package\Locker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises actual command binding, upstream normalization and metadata-only write authority. */
final class NormalizeComposerTest extends TestCase
{
    private string $directory;

    /** Each fixture is a disposable directory without consumer runtime dependencies or Git prerequisites. */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/standards composer café-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    /** Removes only this test's uniquely owned fixture. */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Check preserves every byte; explicit apply preserves meaningful ordering and never executes consumer code. */
    public function testReadOnlyCheckAndIdempotentExplicitApplyPreservePolicy(): void
    {
        $manifest = [
            'scripts' => [
                'test' => ['@second', '@first'],
                'auto-scripts' => ['z' => 'cmd', 'a' => 'cmd'],
                'post-update-cmd' => '@php -r "file_put_contents(\'executed\', \'unexpected\');"',
            ],
            'extra' => [
                'installer-paths' => ['z/{$name}' => ['type:library'], 'a/{$name}' => ['vendor/*']],
                'patches' => ['vendor/package' => ['Z patch' => 'z.patch', 'A patch' => 'a.patch']],
            ],
            'config' => [
                'allow-plugins' => ['vendor/*' => false, '*' => true],
                'preferred-install' => ['vendor/*' => 'source', '*' => 'dist'],
            ],
            'autoload' => ['files' => ['z.php', 'a.php'], 'psr-4' => ['Example\\' => ['z/', 'a/']]],
            'repositories' => [
                'priority' => ['type' => 'composer', 'url' => 'https://priority.example.test'],
                'fallback' => ['type' => 'composer', 'url' => 'https://fallback.example.test'],
            ],
            'keywords' => ['zebra', 'alpha'],
            'description' => 'Synthetic metadata',
            'license' => 'MIT',
            'name' => 'example/fixture',
        ];
        foreach (['require', 'require-dev', 'conflict', 'provide', 'replace'] as $section) {
            $manifest[$section] = ['z/package' => '^2.0 || ^1.0', 'a/package' => 'dev-main as 1.0.x-dev'];
        }
        $source = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($this->directory . '/composer.json', $source);
        file_put_contents($this->directory . '/source.php', '<?php invalid syntax');
        mkdir($this->directory . '/vendor');
        file_put_contents($this->directory . '/vendor/autoload.php', '<?php throw new RuntimeException("Consumer autoload executed");');

        $before = $this->snapshot();
        $check = $this->cli([]);
        self::assertSame(1, $check->getExitCode(), $check->getErrorOutput());
        self::assertSame($before, $this->snapshot());
        $apply = $this->cli(['--apply']);
        self::assertSame(0, $apply->getExitCode(), $apply->getErrorOutput());
        $normalized = json_decode(file_get_contents($this->directory . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('name', array_key_first($normalized));
        self::assertSame($manifest['keywords'], $normalized['keywords']);
        foreach (['require', 'require-dev', 'conflict', 'provide', 'replace'] as $section) {
            self::assertSame(['a/package', 'z/package'], array_keys($normalized[$section]));
            foreach ($manifest[$section] as $package => $constraint) {
                self::assertSame($constraint, $normalized[$section][$package]);
            }
        }
        self::assertSame($manifest['repositories'], $normalized['repositories']);
        foreach (['scripts', 'autoload'] as $section) {
            foreach ($manifest[$section] as $key => $value) {
                self::assertSame($value, $normalized[$section][$key]);
            }
        }
        self::assertSame($manifest['extra'], $normalized['extra']);
        foreach (['allow-plugins', 'preferred-install'] as $section) {
            self::assertSame($manifest['config'][$section], $normalized['config'][$section]);
        }
        $after = $this->snapshot();
        self::assertSame($before['source.php'], $after['source.php']);
        self::assertSame($before['vendor/autoload.php'], $after['vendor/autoload.php']);
        self::assertSame(0, $this->cli([])->getExitCode());
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertSame($after, $this->snapshot());
    }

    /** Lock hashes use Composer's own API; apply reports incompatibility without maintaining the lock. */
    public function testApplyReportsChangedHashAndLeavesLockUntouched(): void
    {
        $source = '{"name":"example/fixture","require":{"z/package":"^2 || ^1","a/package":"^1"}}';
        file_put_contents($this->directory . '/composer.json', $source);
        $lock = json_encode(['content-hash' => Locker::getContentHash($source), 'packages' => [], 'packages-dev' => []]);
        file_put_contents($this->directory . '/composer.lock', $lock);
        $before = $this->snapshot();
        self::assertSame(1, $this->cli([])->getExitCode());
        self::assertSame($before, $this->snapshot());

        $apply = $this->cli(['--apply']);
        self::assertSame(1, $apply->getExitCode(), $apply->getErrorOutput());
        self::assertStringContainsString('content-hash is incompatible after normalization', $apply->getOutput());
        self::assertSame($lock, file_get_contents($this->directory . '/composer.lock'));
        $after = $this->snapshot();
        self::assertNotSame($before['composer.json'], $after['composer.json']);
        self::assertSame(1, $this->cli(['--apply'])->getExitCode());
        self::assertSame($after, $this->snapshot());
    }

    /** An initially stale lock prevents both preview and apply writes. */
    public function testStaleLockPreventsApply(): void
    {
        file_put_contents($this->directory . '/composer.json', '{"name":"example/fixture"}');
        file_put_contents($this->directory . '/composer.lock', '{"content-hash":"stale","packages":[]}');
        $before = $this->snapshot();
        $result = $this->cli(['--apply']);
        self::assertSame(1, $result->getExitCode());
        self::assertStringContainsString('No files changed', $result->getOutput());
        self::assertSame($before, $this->snapshot());
    }

    /** Formatting retains CRLF/tabs and repository priority arrays; a compatible lock remains byte-identical. */
    public function testFormattingAndFreshLockSurviveApply(): void
    {
        $source = "{\r\n\t\"repositories\": [\r\n\t\t{\"type\":\"composer\",\"url\":\"https://z.example.test\"},\r\n\t\t{\"type\":\"composer\",\"url\":\"https://a.example.test\"}\r\n\t],\r\n\t\"name\": \"example/fixture\"\r\n}\r\n";
        file_put_contents($this->directory . '/composer.json', $source);
        // Sorted repository object properties avoid a meaningful hash change in this fixture.
        $lock = json_encode(['content-hash' => Locker::getContentHash($source), 'packages' => []]);
        file_put_contents($this->directory . '/composer.lock', $lock);
        $result = $this->cli(['--apply', '--directory=' . $this->directory]);
        self::assertSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        $normalized = file_get_contents($this->directory . '/composer.json');
        self::assertStringContainsString("\r\n\t\"name\"", $normalized);
        self::assertStringEndsWith("\r\n", $normalized);
        self::assertSame(
            json_decode($source, true)['repositories'],
            json_decode($normalized, true)['repositories'],
        );
        self::assertSame($lock, file_get_contents($this->directory . '/composer.lock'));
        self::assertSame(0, $this->cli([])->getExitCode());
    }

    /** Invalid JSON/schema and missing files fail without writing; symlinks never redirect metadata access. */
    public function testInvalidInputsAndSymlinksFailWithoutWriting(): void
    {
        self::assertSame(2, $this->cli([])->getExitCode());
        foreach (['not JSON', '{"require":42}', '[]'] as $source) {
            file_put_contents($this->directory . '/composer.json', $source);
            self::assertSame(2, $this->cli(['--apply'])->getExitCode());
            self::assertSame($source, file_get_contents($this->directory . '/composer.json'));
        }
        file_put_contents($this->directory . '/composer.json', '{"name":"example/fixture"}');
        foreach (['invalid lock', '42'] as $lock) {
            file_put_contents($this->directory . '/composer.lock', $lock);
            self::assertSame(2, $this->cli(['--apply'])->getExitCode());
            self::assertSame('{"name":"example/fixture"}', file_get_contents($this->directory . '/composer.json'));
            self::assertSame($lock, file_get_contents($this->directory . '/composer.lock'));
        }
        unlink($this->directory . '/composer.lock');
        unlink($this->directory . '/composer.json');
        file_put_contents($this->directory . '/other.json', '{"name":"example/fixture"}');
        symlink($this->directory . '/other.json', $this->directory . '/composer.json');
        self::assertSame(2, $this->cli(['--apply'])->getExitCode());
        self::assertSame('{"name":"example/fixture"}', file_get_contents($this->directory . '/other.json'));
    }

    /** @param list<string> $options Runs without HTTP file streams/cURL execution to detect unexpected schema downloads. */
    private function cli(array $options): Process
    {
        $process = new Process([
            PHP_BINARY, '-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec',
            dirname(__DIR__, 2) . '/bin/code-standards', 'normalize-composer', ...$options,
        ], $this->directory, ['XDEBUG_MODE' => 'off'], timeout: 30);
        $process->run();

        return $process;
    }

    /** @return array<string, string> Complete file-byte inventory catches unexpected writes or generated artifacts. */
    private function snapshot(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[substr($file->getPathname(), strlen($this->directory) + 1)] = file_get_contents($file->getPathname());
        }
        ksort($files);

        return $files;
    }
}
