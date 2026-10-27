<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Distribution;

use PharData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Verifies the actual Git and Composer archive routes against one committed package snapshot. */
final class ArchiveTest extends TestCase
{
    private string $directory;

    /** Copies only owned tracked source into an isolated fixture, including candidate export attributes. */
    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-archive-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $root = dirname(__DIR__, 2);
        $paths = explode("\0", $this->execute(['git', 'ls-files', '-z'], $root));
        $paths[] = '.gitattributes';

        foreach (array_unique($paths) as $path) {
            if ('' === $path || !is_file($root . '/' . $path)) {
                continue;
            }
            $content = file_get_contents($root . '/' . $path);
            self::assertIsString($content);
            $this->put($path, $content);
        }
        $readme = file_get_contents($this->directory . '/README.md');
        self::assertIsString($readme);
        $this->put('README.md', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $readme)));
        $crlfReadme = file_get_contents($this->directory . '/README.md');
        self::assertIsString($crlfReadme);
        self::assertStringContainsString("\r\n", $crlfReadme);
        foreach (['vendor/local.php', '.agents/skills/copied/SKILL.md', '.build/local.php', '.phpunit.cache/local.php'] as $path) {
            $this->put($path, 'fixture-local-state');
        }
        $this->execute(['git', 'init', '-q', '-b', 'main']);
        $this->execute(['git', 'config', 'user.name', 'Archive Fixture']);
        $this->execute(['git', 'config', 'user.email', 'fixture@example.invalid']);
        $this->execute(['git', 'add', '-f', '--all']);
        $this->execute(['git', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-q', '-m', 'fixture']);
        self::assertStringNotContainsString("\r\n", $this->execute(['git', 'show', 'HEAD:README.md']));
        $this->materializeCommittedFiles();
        $normalizedReadme = file_get_contents($this->directory . '/README.md');
        self::assertIsString($normalizedReadme);
        self::assertStringNotContainsString("\r\n", $normalizedReadme);
    }

    /** Removes only the exclusively owned fixture and its generated archives. */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Both routes honor one export-ignore policy while retaining the runtime, canonical skill and README artwork. */
    public function testGitAndComposerExportTheSamePackageFiles(): void
    {
        mkdir($this->directory . '/.build/archives', recursive: true);
        $this->execute(['git', 'archive', '--format=zip', '--output=.build/archives/git.zip', 'HEAD']);
        $this->execute(['composer', 'archive', '--format=zip', '--dir=.build/archives', '--file=composer', '--no-plugins', '--no-scripts', '--no-interaction']);
        $git = $this->contents($this->directory . '/.build/archives/git.zip');
        $composer = $this->contents($this->directory . '/.build/archives/composer.zip');
        self::assertSame($git, $composer);
        foreach (['bin/code-standards', 'config/ecs.php', 'composer.lock', 'README.md', 'CHANGELOG.md', 'LICENSE',
            '.github/actions/check/action.yml', 'resources/workflows/code-standards.yml',
            'resources/skills/fast-forward-code-standards/SKILL.md', 'docs/_static/mascot-banner.png'] as $path) {
            self::assertArrayHasKey($path, $git);
        }
        foreach (array_keys($git) as $path) {
            self::assertDoesNotMatchRegularExpression('~^(?:vendor|\.agents|\.build|\.phpunit\.cache|tests|\.github/workflows)/~', $path);
        }
        self::assertArrayNotHasKey('skills.json', $git);
        self::assertArrayNotHasKey('.github/ReleaseChannelUpdater.php', $git);
        self::assertArrayNotHasKey('.github/update-release-channels.php', $git);
        self::assertArrayNotHasKey('.github/update-release-channels.md', $git);
        self::assertArrayNotHasKey('archive', json_decode(file_get_contents($this->directory . '/composer.json'), true, flags: JSON_THROW_ON_ERROR));
    }

    /** Writes one synthetic or copied fixture file without traversing another checkout. */
    private function put(string $path, string $content): void
    {
        $target = $this->directory . '/' . $path;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), recursive: true);
        }
        file_put_contents($target, $content);
    }

    /** Materializes normalized index bytes after removing only the fixture's copied files. */
    private function materializeCommittedFiles(): void
    {
        $paths = explode("\0", $this->execute(['git', 'ls-files', '-z']));
        foreach ($paths as $path) {
            if ('' === $path) {
                continue;
            }
            unlink($this->directory . '/' . $path);
        }

        $this->execute(['git', '-c', 'core.autocrlf=false', '-c', 'core.eol=lf', 'checkout-index', '--all', '--force']);
    }

    /**
     * Runs native tools with no shell and bounded lifetime; any failed archive is a test failure.
     *
     * @param list<string> $arguments Actual executable and its separate arguments.
     * @param string|null $directory Explicit source root, or the isolated fixture when null.
     */
    private function execute(array $arguments, ?string $directory = null): string
    {
        $process = new Process($arguments, $directory ?? $this->directory, timeout: 60);
        $process->mustRun();

        return $process->getOutput();
    }

    /** @return array<string, string> Sorted archive-relative file names mapped to SHA-256 bytes. */
    private function contents(string $path): array
    {
        $archive = new PharData($path);
        $prefix = 'phar://' . $archive->getPath() . '/';
        $contents = [];
        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            if ($file->isDir()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($prefix));
            $contents[$relative] = hash('sha256', $file->getContent());
        }
        ksort($contents);

        return $contents;
    }
}
