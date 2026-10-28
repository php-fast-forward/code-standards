<?php

declare(strict_types=1);

require __DIR__ . '/support.php';

$source = dirname(__DIR__, 2);
$binary = realpath($argv[1] ?? $source . '/builds/code-standards');
if (false === $binary || !is_executable($binary)) {
    throw new RuntimeException('Expected the compiled builds/code-standards executable.');
}
$root = sys_get_temp_dir() . '/standards-package-' . bin2hex(random_bytes(12));
mkdir($root, 0700);
register_shutdown_function(static fn () => removeTree($root));
$environment = ['COMPOSER_HOME' => $root . '/composer-home', 'COMPOSER_CACHE_DIR' => $root . '/composer-cache',
    'GIT_CONFIG_GLOBAL' => '/dev/null', 'GIT_CONFIG_SYSTEM' => '/dev/null', 'GIT_TERMINAL_PROMPT' => '0'];
$package = $root . '/package';
mkdir($package);
foreach (['composer.json', 'composer.lock', 'LICENSE', 'README.md', 'resources', '.gitattributes'] as $path) {
    copyTree($source . '/' . $path, $package . '/' . $path);
}
copyTree($binary, $package . '/builds/code-standards');
chmod($package . '/builds/code-standards', 0755);
runCommand(['git', 'init', '-q', '-b', 'main'], $package, environment: $environment);
runCommand(['git', 'add', '-f', '.'], $package, environment: $environment);
runCommand(['git', '-c', 'user.name=Package Fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'Disposable compiled fixture'], $package, environment: $environment);
runCommand(['git', 'archive', '--format=zip', '--output=' . $root . '/git.zip', 'HEAD'], $package, environment: $environment);
runCommand(['composer', 'archive', '--format=zip', '--dir=' . $root, '--file=composer', '--no-plugins', '--no-scripts'], $package, environment: $environment);
$contents = static function (string $file): array {
    $archive = new PharData($file);
    $files = [];
    foreach (new RecursiveIteratorIterator($archive) as $entry) {
        if ($entry->isFile()) {
            $files[substr($entry->getPathname(), strlen('phar://' . $file . '/'))] = hash('sha256', $entry->getContent());
        }
    }
    ksort($files);

    return $files;
};
$git = $contents($root . '/git.zip');
if ($git !== $contents($root . '/composer.zip') || !isset($git['builds/code-standards'])) {
    throw new RuntimeException('Archive routes disagree or omit the compiled executable.');
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && ($git[substr($file->getPathname(), strlen($source) + 1)] ?? null) !== hash_file('sha256', $file->getPathname())) {
        throw new RuntimeException('An exported resource changed or disappeared.');
    }
}
$metadata = json_decode(file_get_contents($package . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$metadata['version'] = 'dev-fixture';
$metadata['dist'] = ['type' => 'zip', 'url' => $root . '/git.zip'];
$repositories = [['type' => 'package', 'package' => $metadata], ['packagist.org' => false]];
$results = [];
foreach (['local', 'global'] as $mode) {
    $consumer = $root . '/' . $mode;
    mkdir($consumer);
    writeJson($consumer . '/composer.json', ['name' => 'fixture/consumer', 'repositories' => $repositories]);
    $env = $environment;
    $prefix = ['composer'];
    if ('global' === $mode) {
        $env['COMPOSER_HOME'] = $consumer;
        $prefix[] = 'global';
    }
    runCommand([...$prefix, 'require', '--prefer-dist', '--no-scripts', '--no-plugins', '--no-interaction', 'fast-forward/code-standards:dev-fixture'], $consumer, environment: $env);
    runCommand([...$prefix, 'install', '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction'], $consumer, environment: $env);
    $lock = json_decode(file_get_contents($consumer . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    if (['fast-forward/code-standards'] !== array_column($lock['packages'], 'name') || is_dir($consumer . '/vendor/fast-forward/code-standards/src')) {
        throw new RuntimeException('Consumer received source or external runtime libraries.');
    }
    $proxy = $consumer . '/vendor/bin/code-standards';
    if (!str_contains(file_get_contents($proxy), 'builds/code-standards')) {
        throw new RuntimeException('Composer did not target the compiled executable directly.');
    }
    runCommand([PHP_BINARY, $proxy, 'list', '--raw'], $consumer, environment: $env);
    runCommand(['git', 'init', '-q'], $consumer, environment: $env);
    file_put_contents($consumer . '/source.php', '<?php function values($value) { if ($value === 42) { return array(1, 2); } return array(); }' . PHP_EOL);
    file_put_contents($consumer . '/policy.php', '<?php return \\Rector\\Config\\RectorConfig::configure()->withPhpVersion(80300)->withoutParallel()->withCache(__DIR__."/.cache")->withPaths([__DIR__."/source.php"])->withRules([\\Rector\\Php54\\Rector\\Array_\\LongArrayToShortArrayRector::class]);');
    file_put_contents($consumer . '/ecs.php', <<<'CONFIG'
<?php
if (getcwd() !== __DIR__) {
    throw new RuntimeException('Consumer cwd changed');
}
$runtime = dirname((new ReflectionClass(FastForward\CodeStandards\Config\ECSConfig::class))->getFileName(), 3);
file_put_contents(__DIR__ . '/probe.json', json_encode(['runtime' => $runtime, 'mode' => fileperms($runtime) & 0777, 'identity' => json_decode(file_get_contents($runtime . '/distribution-manifest.json'), true)]));
return FastForward\CodeStandards\Config\ECSConfig::configure();
CONFIG);
    file_put_contents($consumer . '/composer.lock', '{"fixture":"untouched"}');
    runCommand(['git', 'add', 'source.php'], $consumer, environment: $env);
    $inputs = ['source.php', 'policy.php', 'ecs.php', 'composer.json', 'composer.lock'];
    $before = array_map(static fn (string $path) => hash_file('sha256', $consumer . '/' . $path), $inputs);
    $check = runCommand([PHP_BINARY, $proxy, 'check', '--all', '--config=ecs.php'], $consumer, 2, $env);
    $probe = json_decode(file_get_contents($consumer . '/probe.json'), true, flags: JSON_THROW_ON_ERROR);
    if (0700 !== $probe['mode'] || file_exists($probe['runtime']) || 'fast-forward/code-standards' !== $probe['identity']['root']['name']) {
        throw new RuntimeException('Private runtime identity or cleanup failed.');
    }
    $rector = runCommand([PHP_BINARY, $proxy, 'refactor', '--config=policy.php'], $consumer, 2, $env);
    $mnd = runCommand([PHP_BINARY, $proxy, 'magic-numbers', '--all'], $consumer, 1, $env);
    $normalized = runCommand([PHP_BINARY, $proxy, 'normalize-composer'], $consumer, 1, $env);
    if (!str_contains($normalized, 'composer.lock is not fresh')) {
        throw new RuntimeException('Bundled Composer schema/lock diagnostic missing.');
    }
    if (extension_loaded('dom')) {
        $xml = runCommand([PHP_BINARY, $proxy, 'magic-numbers', '--all', '--format=xml'], $consumer, 1, $env);
        $document = new DOMDocument();
        if (!$document->loadXML($xml) || (int) $document->documentElement->getAttribute('errorCount') < 1) {
            throw new RuntimeException('Native XML report did not preserve findings.');
        }
    }
    $after = array_map(static fn (string $path) => hash_file('sha256', $consumer . '/' . $path), $inputs);
    if (!str_contains($check, 'begin diff') || !str_contains($rector, 'LongArrayToShortArrayRector') || !str_contains($mnd, '42') || $before !== $after) {
        throw new RuntimeException('Native runtime output or source preservation failed.');
    }
    file_put_contents($consumer . '/vendor/autoload.php', '<?php throw new RuntimeException("Unexpected consumer loader");');
    runCommand([PHP_BINARY, $proxy, 'list', '--raw'], $consumer, environment: $env);
    $results[$mode] = ['packages' => array_column($lock['packages'], 'name'), 'native_exits' => ['check' => 2, 'refactor' => 2, 'magic-numbers' => 1]];
}
fwrite(STDOUT, json_encode(['php' => PHP_VERSION, 'bytes' => filesize($binary), 'sha256' => hash_file('sha256', $binary), 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
