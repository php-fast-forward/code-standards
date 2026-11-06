<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises the native builder in fresh processes so each installation owns its class and policy files. */
final class CacheLifetimeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-cache-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir([$this->directory . '/consumer', $this->directory . '/temp']);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Repeated source invocations reuse one cache, while policy and installation revisions stay separate. */
    public function testSourceCachesRemainStableAndInvalidateOwnedPolicy(): void
    {
        $first = $this->installation('source-a');
        $second = $this->installation('source-b');
        $cache = $this->cache($first);
        self::assertStringStartsWith($this->directory . '/temp/fast-forward-code-standards/', $cache);
        self::assertSame($cache, $this->cache($first));
        self::assertNotSame($cache, $this->cache($second));
        file_put_contents($first . '/src/Sniff/MultilineBooleanConditionSniff.php', "\n// Changed owned policy fixture.\n", FILE_APPEND);
        self::assertNotSame($cache, $this->cache($first));
    }

    /** Two independently extracted versions cannot share a default cache or leak it outside their runtime trees. */
    public function testExtractedCachesBelongToTheirRuntimeAndPreserveExplicitOverrides(): void
    {
        $first = $this->installation('runtime-a');
        $second = $this->installation('runtime-b');
        file_put_contents($first . '/distribution-manifest.json', '{"version":"a"}');
        file_put_contents($second . '/distribution-manifest.json', '{"version":"b"}');
        $cache = $this->cache($first);
        self::assertStringStartsWith($first . '/.cache/ecs/', $cache);
        self::assertSame($cache, $this->cache($first));
        self::assertStringStartsWith($second . '/.cache/ecs/', $this->cache($second));
        self::assertNotSame($cache, $this->cache($second));
        self::assertNotSame($cache, $this->cache($this->installation('source')));
        self::assertSame($this->directory . '/explicit-cache', $this->cache($first, $this->directory . '/explicit-cache'));
    }

    private function installation(string $name): string
    {
        $root = dirname(__DIR__, 2);
        $installation = $this->directory . '/' . $name;
        foreach (['Config/ECSConfig.php', 'Sniff/MultilineBooleanConditionSniff.php', 'Sniff/MultilineConditionSpacingSniff.php'] as $file) {
            (new Filesystem())->mkdir(dirname($installation . '/src/' . $file));
            copy($root . '/src/' . $file, $installation . '/src/' . $file);
        }

        return $installation;
    }

    /** Reads the native public cache parameter, without inspecting this tool's private methods. */
    private function cache(string $installation, ?string $override = null): string
    {
        $probe = <<<'PHP'
require $argv[1];
require dirname($argv[1]) . '/symplify/easy-coding-standard/vendor/squizlabs/php_codesniffer/autoload.php';
require $argv[2] . '/src/Config/ECSConfig.php';
require $argv[2] . '/src/Sniff/MultilineBooleanConditionSniff.php';
require $argv[2] . '/src/Sniff/MultilineConditionSpacingSniff.php';
$builder = FastForward\CodeStandards\Config\ECSConfig::configure(
    isset($argv[3]) ? static fn($config) => $config->withCache($argv[3]) : null,
);
$builder(new Symplify\EasyCodingStandard\Config\ECSConfig());
echo Symplify\EasyCodingStandard\DependencyInjection\SimpleParameterProvider::getStringParameter(
    Symplify\EasyCodingStandard\ValueObject\Option::CACHE_DIRECTORY,
);
PHP;
        $arguments = [PHP_BINARY, '-r', $probe, dirname(__DIR__, 2) . '/vendor/autoload.php', $installation];
        if (null !== $override) {
            $arguments[] = $override;
        }
        $process = new Process($arguments, $this->directory . '/consumer', ['TMPDIR' => $this->directory . '/temp'], timeout: 30);
        $process->mustRun();

        return $process->getOutput();
    }
}
