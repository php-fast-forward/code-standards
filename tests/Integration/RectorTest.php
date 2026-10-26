<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Real installed CLI and native rules; only disposable consumer files are touched. */
final class RectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/rector-consumer-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory . '/composer.lock', '{"fixture":"do not update"}');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    /** Native preview leaves source/config/lock bytes intact; explicit apply is verified and repeatable. */
    public function testPreviewApplyAndSecondPassWithExplicitNativeConfiguration(): void
    {
        $source = "<?php\nfunction values() { return array(1, 2); }\n";
        file_put_contents($this->directory . '/source.php', $source);
        $config = $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 70400);
        $before = $this->ownedBytes();

        $preview = $this->cli();
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        self::assertDirectoryExists($this->directory . '/.cache');
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertStringContainsString('[1, 2]', file_get_contents($this->directory . '/source.php'));
        $after = $this->ownedBytes();
        self::assertSame(0, $this->cli()->getExitCode());
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertSame($after, $this->ownedBytes());
        self::assertSame($config, file_get_contents($this->directory . '/policy.php'));
    }

    /** Empty native custom config is rejected before Rector's successful-but-writing initializer. */
    public function testEmptyConfigDoesNotGenerateRectorFile(): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        file_put_contents($this->directory . '/policy.php', '<?php return \\Rector\\Config\\RectorConfig::configure();');
        $before = $this->ownedBytes();
        $result = $this->cli();

        self::assertSame(2, $result->getExitCode());
        self::assertStringContainsString('at least one rule', $result->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/rector.php');
        self::assertSame($before, $this->ownedBytes());
    }

    /** A target below the executing PHP suppresses a versioned transformation without selecting migration sets. */
    public function testConsumerTargetControlsVersionedRule(): void
    {
        $source = '<?php class Example { private string $name; public function __construct(string $name) { $this->name = $name; } }';
        file_put_contents($this->directory . '/source.php', $source);
        $rule = '\\Rector\\Php80\\Rector\\Class_\\ClassPropertyAssignToConstructorPromotionRector';
        $this->configuration($rule, 70400);
        self::assertSame(0, $this->cli()->getExitCode());
        $this->configuration($rule, 80000);
        self::assertSame(2, $this->cli()->getExitCode());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
    }

    /** The subprocess boundary protects the parent, but a consumer autoload collision still fails in the child. */
    public function testConsumerAutoloadCollisionIsNotClaimedToBeIsolated(): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 70400);
        mkdir($this->directory . '/vendor');
        file_put_contents($this->directory . '/vendor/autoload.php', '<?php namespace Rector\\Config; final class RectorConfig {}');
        $before = $this->ownedBytes();
        $result = $this->cli();

        self::assertNotSame(0, $result->getExitCode());
        self::assertSame($before, $this->ownedBytes());
        unlink($this->directory . '/vendor/autoload.php');
        self::assertSame(2, $this->cli()->getExitCode());
    }

    /** Native bootstrap failures preserve their status instead of being mistaken for no changes. */
    public function testExecutableConfigFailureAndRequiredOption(): void
    {
        file_put_contents($this->directory . '/policy.php', '<?php throw new RuntimeException("native config failure");');
        $result = $this->cli();
        self::assertSame(1, $result->getExitCode());
        self::assertStringContainsString('native config failure', $result->getErrorOutput());
        $missing = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'refactor'], $this->directory, timeout: 20);
        self::assertSame(2, $missing->run());
    }

    /** Returns native builder PHP unchanged; target and cache remain consumer-owned options. */
    private function configuration(string $rule, int $target): string
    {
        $config = '<?php return \\Rector\\Config\\RectorConfig::configure()->withPhpVersion(' . $target . ')'
            . '->withoutParallel()->withCache(__DIR__."/.cache")->withPaths([__DIR__."/source.php"])'
            . '->withRules([' . $rule . '::class]);';
        file_put_contents($this->directory . '/policy.php', $config);

        return $config;
    }

    /** @return array<string, string> Bytes owned by the consumer; configured generated cache is deliberately separate. */
    private function ownedBytes(): array
    {
        $bytes = [];
        foreach (['source.php', 'policy.php', 'composer.lock'] as $name) {
            if (is_file($this->directory . '/' . $name)) {
                $bytes[$name] = file_get_contents($this->directory . '/' . $name);
            }
        }

        return $bytes;
    }

    /** @param list<string> $options Additional explicit command options, never shell fragments. */
    private function cli(array $options = []): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'refactor', '--config=policy.php', ...$options], $this->directory, timeout: 90);
        $process->run();

        return $process;
    }
}
