<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $preview = $this->cli(environment: ['CI' => 'true', 'GITHUB_ACTIONS' => 'true']);
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        // Rector 2.8 uses its configured file cache in CI; owned source/config/lock bytes stay intact.
        self::assertDirectoryExists($this->directory . '/.cache');
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertStringContainsString('[1, 2]', file_get_contents($this->directory . '/source.php'));
        $after = $this->ownedBytes();
        self::assertSame(0, $this->cli()->getExitCode());
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertSame($after, $this->ownedBytes());
        self::assertSame($config, file_get_contents($this->directory . '/policy.php'));
    }

    /** Preserves interface documentation during refactoring. */
    public function testNativeArrayRulePreservesNormativeInterfaceDocumentation(): void
    {
        $fixtures = dirname(__DIR__) . '/Fixtures/';
        $source = file_get_contents($fixtures . 'normative-contract.before.php.inc');
        $expected = file_get_contents($fixtures . 'normative-contract.after.php.inc');
        self::assertIsString($source);
        self::assertIsString($expected);
        file_put_contents($this->directory . '/source.php', $source);
        $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 70400);
        $before = $this->ownedBytes();

        $preview = $this->cli();
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        $apply = $this->cli(['--apply']);
        self::assertSame(0, $apply->getExitCode(), $apply->getOutput() . $apply->getErrorOutput());
        self::assertSame($expected, file_get_contents($this->directory . '/source.php'));
        $after = $this->ownedBytes();
        self::assertSame(0, $this->cli()->getExitCode());
        self::assertSame(0, $this->cli(['--apply'])->getExitCode());
        self::assertSame($after, $this->ownedBytes());
    }

    /** Exercises PHPStan's rich parser on modern source before preview, apply and verification. */
    public function testRichParserCompatibilityWithModernConsumerSource(): void
    {
        $fixtures = dirname(__DIR__) . '/Fixtures/';
        $source = file_get_contents($fixtures . 'rector-rich-parser.before.php.inc');
        $expected = file_get_contents($fixtures . 'rector-rich-parser.after.php.inc');
        file_put_contents($this->directory . '/source.php', $source);
        $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 80300);
        $before = $this->ownedBytes();

        $preview = $this->cli();
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        $apply = $this->cli(['--apply']);
        self::assertSame(0, $apply->getExitCode(), $apply->getOutput() . $apply->getErrorOutput());
        self::assertSame($expected, file_get_contents($this->directory . '/source.php'));
        $verify = $this->cli();
        self::assertSame(0, $verify->getExitCode(), $verify->getOutput() . $verify->getErrorOutput());
    }

    /** Empty native custom config is rejected before Rector's successful-but-writing initializer. */
    #[DataProvider('executionModes')]
    public function testEmptyConfigDoesNotGenerateRectorFile(array $options): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        file_put_contents($this->directory . '/policy.php', '<?php return \\Rector\\Config\\RectorConfig::configure()->withPhpVersion(70400);');
        $before = $this->ownedBytes();
        $result = $this->cli($options);

        self::assertSame(2, $result->getExitCode());
        self::assertStringContainsString('at least one rule', $result->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/rector.php');
        self::assertSame($before, $this->ownedBytes());
    }

    /** Native skips must leave an effective rule before either execution mode can proceed. */
    #[DataProvider('executionModes')]
    public function testFullySkippedRulesFailWithoutWriting(array $options): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        $rule = '\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector';
        $config = $this->configuration($rule, 70400);
        file_put_contents($this->directory . '/policy.php', str_replace('->withRules(', '->withSkip([' . $rule . '::class])->withRules(', $config));
        $before = $this->ownedBytes();
        $result = $this->cli($options);

        self::assertSame(2, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        self::assertStringContainsString('at least one rule', $result->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/rector.php');
        self::assertSame($before, $this->ownedBytes());
    }

    /** An explicitly configured renaming rule remains valid despite appearing as an unconfigured native helper. */
    public function testExplicitConfiguredRenameRuleCountsAsSelectedRule(): void
    {
        $source = '<?php function create(): \\DateTime { return new \\DateTime(); }';
        file_put_contents($this->directory . '/source.php', $source);
        $rule = '\\Rector\\Renaming\\Rector\\Name\\RenameClassRector';
        $config = $this->configuration($rule, 70400);
        $config = str_replace('->withRules([' . $rule . '::class])',
            '->withConfiguredRule(' . $rule . '::class, [\\DateTime::class => \\DateTimeImmutable::class])', $config);
        file_put_contents($this->directory . '/policy.php', $config);
        $before = $this->ownedBytes();
        $preview = $this->cli();
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        $apply = $this->cli(['--apply']);
        self::assertSame(0, $apply->getExitCode(), $apply->getOutput() . $apply->getErrorOutput());
        self::assertStringContainsString('DateTimeImmutable', file_get_contents($this->directory . '/source.php'));
        $verify = $this->cli();
        self::assertSame(0, $verify->getExitCode(), $verify->getOutput() . $verify->getErrorOutput());
        self::assertSame($config, file_get_contents($this->directory . '/policy.php'));
    }

    /** @return iterable<string, array{list<string>}> Both preview and application require a declared target. */
    public static function executionModes(): iterable
    {
        yield 'preview' => [[]];
        yield 'apply' => [['--apply']];
    }

    /** Valid rules must not silently inherit the executing runtime as the consumer PHP target. */
    #[DataProvider('executionModes')]
    public function testMissingExplicitTargetRejectsBeforeProcessing(array $options): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        file_put_contents($this->directory . '/composer.json', '{"require":{"php":"^7.4"}}');
        $config = $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 70400);
        file_put_contents($this->directory . '/policy.php', str_replace('->withPhpVersion(70400)', '', $config));
        $before = $this->ownedBytes();
        $result = $this->cli($options);

        self::assertSame(1, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        self::assertStringContainsString('explicit PHP target', $result->getErrorOutput());
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

    /** Native import and service bindings preserve cwd/config context and evaluation counts through the preflight. */
    public function testImportedExplicitTargetAndNativeServicesPreserveConfigurationContext(): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        file_put_contents($this->directory . '/relative.txt', 'consumer context');
        file_put_contents($this->directory . '/parts.php', <<<'PHP'
            <?php
            return static function (\Rector\Config\RectorConfig $config): void {
                $config->phpVersion(70400);
                $config->paths([__DIR__ . '/source.php']);
                $config->rule(\Rector\Php54\Rector\Array_\LongArrayToShortArrayRector::class);
                $config->disableParallel();
            };
            PHP);
        file_put_contents($this->directory . '/policy.php', <<<'PHP'
            <?php
            return static function (\Rector\Config\RectorConfig $config): void {
                $config->import(__DIR__ . '/parts.php');
                $config->make(\PHPStan\Parser\Parser::class);
                file_put_contents(__DIR__ . '/context.jsonl', json_encode([
                    'cwd' => getcwd(),
                    'directory' => __DIR__,
                    'relative' => file_get_contents('relative.txt'),
                ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
            };
            PHP);
        $before = $this->ownedBytes();
        $native = [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/rector/rector/bin/rector'];
        $inventory = new Process([...$native, 'list-rules', '--config=policy.php', '--output-format=json'], $this->directory, timeout: 90);
        $inventory->mustRun();
        $inventoryLoads = count(file($this->directory . '/context.jsonl', FILE_IGNORE_NEW_LINES));
        unlink($this->directory . '/context.jsonl');
        $processing = new Process([...$native, 'process', '--config=policy.php', '--dry-run', '--no-progress-bar'], $this->directory, timeout: 90);
        self::assertSame(2, $processing->run(), $processing->getOutput() . $processing->getErrorOutput());
        $processLoads = count(file($this->directory . '/context.jsonl', FILE_IGNORE_NEW_LINES));
        unlink($this->directory . '/context.jsonl');
        $preview = $this->cli();
        self::assertSame(2, $preview->getExitCode(), $preview->getOutput() . $preview->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        $lines = file($this->directory . '/context.jsonl', FILE_IGNORE_NEW_LINES);
        self::assertCount($inventoryLoads + $processLoads, $lines);
        foreach ($lines as $line) {
            self::assertSame([
                'cwd' => $this->directory,
                'directory' => $this->directory,
                'relative' => 'consumer context',
            ], json_decode($line, true, flags: JSON_THROW_ON_ERROR));
        }
        $apply = $this->cli(['--apply']);
        self::assertSame(0, $apply->getExitCode(), $apply->getOutput() . $apply->getErrorOutput());
        self::assertCount(2 * $inventoryLoads + 3 * $processLoads, file($this->directory . '/context.jsonl', FILE_IGNORE_NEW_LINES));
        self::assertStringContainsString('[1]', file_get_contents($this->directory . '/source.php'));
    }

    /** Native invalid target/config errors must fail without processing or mutating selected source. */
    public function testInvalidExplicitTargetFailsWithoutWriting(): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        foreach ([0, -1, 1] as $target) {
            $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', $target);
            $before = $this->ownedBytes();
            $result = $this->cli(['--apply']);
            self::assertNotSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
            self::assertSame($before, $this->ownedBytes());
            self::assertFileDoesNotExist($this->directory . '/rector.php');
        }
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

    /** A real native preflight timeout is an execution failure and removes its owned guard without source writes. */
    public function testNativePreflightTimeoutPreservesConsumerAndCleansGuard(): void
    {
        file_put_contents($this->directory . '/source.php', '<?php return array(1);');
        $config = $this->configuration('\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector', 70400);
        $slow = '<?php file_put_contents(__DIR__."/preflight.path", substr($_SERVER["argv"][2], strlen("--config="))); usleep(4000000);'
            . substr($config, strlen('<?php'));
        file_put_contents($this->directory . '/policy.php', $slow);
        $before = $this->ownedBytes();
        $result = $this->cli(['--timeout=2']);

        self::assertSame(1, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        self::assertMatchesRegularExpression('~exceeded\s+the\s+timeout~', $result->getErrorOutput());
        self::assertSame($before, $this->ownedBytes());
        $preflight = file_get_contents($this->directory . '/preflight.path');
        self::assertStringContainsString('/code-standards-rector-', $preflight);
        self::assertFileDoesNotExist($preflight);
        self::assertDirectoryDoesNotExist(dirname($preflight));

        file_put_contents($this->directory . '/policy.php', $config);
        $accepted = $this->cli(['--timeout=5']);
        self::assertSame(2, $accepted->getExitCode(), $accepted->getOutput() . $accepted->getErrorOutput());
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
        foreach (['source.php', 'policy.php', 'composer.json', 'composer.lock'] as $name) {
            if (is_file($this->directory . '/' . $name)) {
                $bytes[$name] = file_get_contents($this->directory . '/' . $name);
            }
        }

        return $bytes;
    }

    /**
     * @param list<string> $options Additional explicit command options, never shell fragments.
     *
     * @param array<string, string> $environment Explicit fixture environment, inherited by the native child.
     */
    private function cli(array $options = [], array $environment = []): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'refactor', '--config=policy.php', ...$options], $this->directory, $environment, timeout: 90);
        $process->run();

        return $process;
    }
}
