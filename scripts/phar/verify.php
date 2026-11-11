<?php

declare(strict_types=1);

require __DIR__ . '/support.php';

$source = dirname(__DIR__, 2);
$binary = realpath($argv[1] ?? $source . '/dist/code-standards');
if (false === $binary || !is_executable($binary)) {
    throw new RuntimeException('Expected the compiled dist/code-standards executable.');
}
$root = sys_get_temp_dir() . '/standards-package-' . bin2hex(random_bytes(12));
mkdir($root, 0700);
mkdir($root . '/temporary', 0700);
register_shutdown_function(static fn() => removeTree($root));
copy($binary, $root . '/inspect.phar');
$payload = new Phar($root . '/inspect.phar');
foreach (inputHashes($source) as $path => $expected) {
    if (!isset($payload[$path]) || hash('sha256', $payload[$path]->getContent()) !== $expected) {
        throw new RuntimeException('Compiled source/resource bytes changed or disappeared: ' . $path);
    }
}
unset($payload);
$environment = ['COMPOSER_HOME' => $root . '/composer-home', 'COMPOSER_CACHE_DIR' => $root . '/composer-cache',
    'GIT_CONFIG_GLOBAL' => '/dev/null', 'GIT_CONFIG_SYSTEM' => '/dev/null', 'GIT_TERMINAL_PROMPT' => '0', 'TMPDIR' => $root . '/temporary'];
$package = $root . '/package';
mkdir($package);
foreach (['src', 'config', 'bin', 'docs', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'resources', '.gitattributes'] as $path) {
    copyTree($source . '/' . $path, $package . '/' . $path);
}
copyTree($binary, $package . '/dist/code-standards');
chmod($package . '/dist/code-standards', 0755);
runCommand(['git', 'init', '-q', '-b', 'main'], $package, environment: $environment);
runCommand(['git', 'add', '-f', '.'], $package, environment: $environment);
runCommand(['git', '-c', 'user.name=Package Fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '-qm', 'Disposable compiled fixture'], $package, environment: $environment);
$reference = trim(runCommand(['git', 'rev-parse', 'HEAD'], $package, environment: $environment));
runCommand(['git', 'archive', '--format=zip', '--output=' . $root . '/git.zip', 'HEAD'], $package, environment: $environment);
runCommand(['composer', 'archive', '--format=zip', '--dir=' . $root, '--file=composer', '--no-plugins', '--no-scripts'], $package, environment: $environment);
$contents = static function (string $file): array {
    $archive = new PharData($file);
    $prefix = 'phar://' . $archive->getPath() . '/';
    $files = [];
    foreach (new RecursiveIteratorIterator($archive) as $entry) {
        if ($entry->isFile()) {
            $files[substr($entry->getPathname(), strlen($prefix))] = hash('sha256', $entry->getContent());
        }
    }
    ksort($files);

    return $files;
};
$git = $contents($root . '/git.zip');
if ($git !== $contents($root . '/composer.zip') || !isset($git['dist/code-standards'])) {
    throw new RuntimeException('Archive routes disagree or omit the compiled executable.');
}
foreach (array_keys($git) as $path) {
    if (preg_match('~^(?:src|config|bin|tests|scripts)/~', $path)) {
        throw new RuntimeException('Source/development input leaked into the ready archive: ' . $path);
    }
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && ($git[substr($file->getPathname(), strlen($source) + 1)] ?? null) !== hash_file('sha256', $file->getPathname())) {
        throw new RuntimeException('An exported resource changed or disappeared.');
    }
}
$metadata = json_decode(file_get_contents($package . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$metadata['version'] = 'dev-fixture';
$metadata['dist'] = ['type' => 'zip', 'url' => $root . '/git.zip'];
$metadata['source'] = ['type' => 'git', 'url' => $package, 'reference' => $reference];
$repositories = [['type' => 'package', 'package' => $metadata], ['packagist.org' => false]];
$results = [];
foreach (['local', 'global', 'source'] as $mode) {
    $consumer = $root . '/' . $mode;
    mkdir($consumer);
    writeJson($consumer . '/composer.json', ['name' => 'fixture/consumer', 'repositories' => $repositories]);
    $env = $environment;
    $prefix = ['composer'];
    if ('global' === $mode) {
        $env['COMPOSER_HOME'] = $consumer;
        $prefix[] = 'global';
    }
    runCommand([...$prefix, 'require', 'source' === $mode ? '--prefer-source' : '--prefer-dist', '--no-scripts', '--no-plugins', '--no-interaction', 'fast-forward/code-standards:dev-fixture'], $consumer, environment: $env);
    runCommand([...$prefix, 'install', '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction'], $consumer, environment: $env);
    $lock = json_decode(file_get_contents($consumer . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    if (['fast-forward/code-standards'] !== array_column($lock['packages'], 'name') || ('source' === $mode) !== is_dir($consumer . '/vendor/fast-forward/code-standards/src')) {
        throw new RuntimeException('Consumer received source or external runtime libraries.');
    }
    $proxy = $consumer . '/vendor/bin/code-standards';
    if (!str_contains(file_get_contents($proxy), 'dist/code-standards')) {
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
    $before = array_map(static fn(string $path) => hash_file('sha256', $consumer . '/' . $path), $inputs);
    $check = runCommand([PHP_BINARY, $proxy, 'check', '--all', '--config=ecs.php'], $consumer, 2, $env);
    $probe = json_decode(file_get_contents($consumer . '/probe.json'), true, flags: JSON_THROW_ON_ERROR);
    if (hash_file('sha256', $source . '/composer.lock') !== $probe['identity']['composer_lock_sha256'] || inputHashes($source) !== $probe['identity']['source_files_sha256']) {
        throw new RuntimeException('Compiled payload does not match the current source and lock.');
    }
    if (buildHashes($source) !== ($probe['identity']['build_recipe_sha256'] ?? null)) {
        throw new RuntimeException('Compiled payload does not match the current PHP build recipe.');
    }
    if (hash_file('sha256', $source . '/composer.json') !== ($probe['identity']['composer_json_sha256'] ?? null)) {
        throw new RuntimeException('Compiled payload does not match the current Composer build manifest.');
    }
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
    $after = array_map(static fn(string $path) => hash_file('sha256', $consumer . '/' . $path), $inputs);
    if (!str_contains($check, 'begin diff') || !str_contains($rector, 'LongArrayToShortArrayRector') || !str_contains($mnd, '42') || $before !== $after) {
        throw new RuntimeException('Native runtime output or source preservation failed.');
    }
    $fixedSource = null;
    foreach (['fix', 'check', 'fix', 'check'] as $operation) {
        runCommand([PHP_BINARY, $proxy, $operation, '--all', '--config=ecs.php'], $consumer, environment: $env);
        $sourceHash = hash_file('sha256', $consumer . '/source.php');
        if (null !== $fixedSource && $sourceHash !== $fixedSource) {
            throw new RuntimeException('Repeated clean check/fix changed formatted source.');
        }
        $fixedSource = $sourceHash;
        $repeatProbe = json_decode(file_get_contents($consumer . '/probe.json'), true, flags: JSON_THROW_ON_ERROR);
        if (file_exists($repeatProbe['runtime']) || is_dir($root . '/temporary/fast-forward-code-standards')) {
            throw new RuntimeException('Repeated compiled operations leaked a runtime or persistent default ECS cache.');
        }
    }
    file_put_contents($consumer . '/override.php', '<?php return FastForward\\CodeStandards\\Config\\ECSConfig::configure()->withCache(__DIR__ . "/explicit-cache");');
    runCommand([PHP_BINARY, $proxy, 'check', '--all', '--config=override.php'], $consumer, environment: $env);
    if (!is_dir($consumer . '/explicit-cache')) {
        throw new RuntimeException('Explicit consumer cache override was removed with the extracted runtime.');
    }
    file_put_contents($consumer . '/vendor/autoload.php', '<?php throw new RuntimeException("Unexpected consumer loader");');
    runCommand([PHP_BINARY, $proxy, 'list', '--raw'], $consumer, environment: $env);
    $results[$mode] = [
        'packages' => array_column($lock['packages'], 'name'),
        'native_exits' => ['check' => 2, 'refactor' => 2, 'magic-numbers' => 1],
        'repeated_cache_operations' => ['fix', 'check', 'fix', 'check'],
        'default_cache_residue' => false,
        'explicit_cache_preserved' => true,
        'consumer_rules' => verifyConsumerRules($proxy, $env),
    ];
}
fwrite(STDOUT, json_encode(['php' => PHP_VERSION, 'bytes' => filesize($binary), 'sha256' => hash_file('sha256', $binary), 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);

/** Exercises real Composer autoload/autoload-dev and a local development package through the selected executable. */
function verifyConsumerRules(string $executable, array $environment): array
{
    $directory = $environment['TMPDIR'] . '/consumer-rules-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    register_shutdown_function(static fn() => removeTree($directory));
    $template = file_get_contents(dirname(__DIR__, 2) . '/tests/Fixtures/BootstrapFixer.php.inc');
    $rules = [
        'FixtureRuntime' => 'runtime-rules',
        'FixtureDevelopment' => 'development-rules',
        'FixturePackage' => 'package/src',
    ];
    foreach ($rules as $namespace => $path) {
        mkdir($directory . '/' . $path, 0755, true);
        file_put_contents($directory . '/' . $path . '/BootstrapFixer.php', str_replace(
            ['CodeStandardsFixture', 'Fixture/replace_marker', 'needs-fix', "'fixed'"],
            [$namespace, $namespace . '/replace_marker', $namespace . '-before', "'" . $namespace . "-after'"],
            $template,
        ));
    }
    writeJson($directory . '/package/composer.json', [
        'name' => 'fixture/development-rules',
        'version' => '1.0.0',
        'autoload' => ['psr-4' => ['FixturePackage\\' => 'src/']],
    ]);
    writeJson($directory . '/composer.json', [
        'name' => 'fixture/consumer-rules',
        'repositories' => [['type' => 'path', 'url' => 'package', 'options' => ['symlink' => false]], ['packagist.org' => false]],
        'require-dev' => ['fixture/development-rules' => '1.0.0'],
        'autoload' => ['psr-4' => ['FixtureRuntime\\' => 'runtime-rules/']],
        'autoload-dev' => ['psr-4' => ['FixtureDevelopment\\' => 'development-rules/']],
    ]);
    runCommand(['composer', 'update', '--no-plugins', '--no-scripts', '--no-interaction', '--no-progress', '--no-audit'], $directory, environment: $environment);
    $installed = json_decode(file_get_contents($directory . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    if (!in_array('fixture/development-rules', $installed['dev-package-names'], true)) {
        throw new RuntimeException('Fixture rule package was not installed as a development dependency.');
    }
    $source = "<?php\n\ndeclare(strict_types=1);\n\n";
    foreach (array_keys($rules) as $namespace) {
        $source .= "echo '" . $namespace . "-before';\n";
    }
    file_put_contents($directory . '/source.php', $source);
    file_put_contents($directory . '/ecs.php', <<<'CONFIG'
<?php

require __DIR__ . '/vendor/autoload.php';
file_put_contents(__DIR__ . '/config-executed', 'explicit');

return FastForward\CodeStandards\Config\ECSConfig::configure()->withRules([
    FixtureRuntime\BootstrapFixer::class,
    FixtureDevelopment\BootstrapFixer::class,
    FixturePackage\BootstrapFixer::class,
]);
CONFIG);
    runCommand(['git', 'init', '-q'], $directory, environment: $environment);
    runCommand(['git', 'add', 'source.php'], $directory, environment: $environment);
    $metadata = ['composer.json', 'composer.lock', 'vendor/autoload.php', 'ecs.php'];
    $before = array_map(static fn(string $path) => hash_file('sha256', $directory . '/' . $path), $metadata);
    runCommand([PHP_BINARY, $executable, 'check', '--all'], $directory, environment: $environment);
    if (file_exists($directory . '/config-executed')) {
        throw new RuntimeException('Consumer ECS configuration was implicitly discovered.');
    }
    $output = runCommand([PHP_BINARY, $executable, 'check', '--all', '--config=ecs.php'], $directory, 2, $environment);
    if ($source !== file_get_contents($directory . '/source.php') || !is_file($directory . '/config-executed')) {
        throw new RuntimeException('Explicit consumer configuration did not run read-only.');
    }
    foreach (array_keys($rules) as $namespace) {
        if (!str_contains($output, $namespace . '-after')) {
            throw new RuntimeException('Consumer rule did not detect its fixture: ' . $namespace);
        }
    }
    runCommand([PHP_BINARY, $executable, 'fix', '--all', '--config=ecs.php'], $directory, environment: $environment);
    $fixed = file_get_contents($directory . '/source.php');
    foreach (array_keys($rules) as $namespace) {
        if (!str_contains($fixed, "'" . $namespace . "-after'") || str_contains($fixed, $namespace . '-before')) {
            throw new RuntimeException('Consumer rule did not fix its fixture: ' . $namespace);
        }
    }
    runCommand([PHP_BINARY, $executable, 'check', '--all', '--config=ecs.php'], $directory, environment: $environment);
    if ($fixed !== file_get_contents($directory . '/source.php') || $before !== array_map(static fn(string $path) => hash_file('sha256', $directory . '/' . $path), $metadata)) {
        throw new RuntimeException('Consumer verification changed source or Composer/configuration inputs.');
    }

    return ['autoload' => true, 'autoload_dev' => true, 'require_dev_rule' => true, 'check_exit' => 2, 'fix_exit' => 0, 'recheck_exit' => 0];
}
