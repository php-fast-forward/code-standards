<?php

declare(strict_types=1);

// Resolve exact locked runtime roots in a disposable tree, never the checkout.
require __DIR__ . '/support.php';

$root = dirname(__DIR__, 2);
$output = $argv[1] ?? $root . '/builds/code-standards';
if ($argc > 2 || file_exists($output)) {
    throw new RuntimeException('Usage: php -d phar.readonly=0 scripts/phar/build.php [NEW_OUTPUT]');
}
$runtime = sys_get_temp_dir() . '/standards-build-' . bin2hex(random_bytes(12));
mkdir($runtime, 0700);
register_shutdown_function(static fn () => removeTree($runtime));
foreach (['src', 'config', 'resources', 'bin', 'LICENSE'] as $path) {
    copyTree($root . '/' . $path, $runtime . '/' . $path);
}
$source = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$sourceIdentity = require $root . '/vendor/composer/installed.php';
$lock = json_decode(file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$packages = array_merge($lock['packages'], $lock['packages-dev']);
$versions = array_column($packages, 'version', 'name');
$require = $source['require'];
foreach ($source['extra']['phar']['runtime-packages'] as $name) {
    $require[$name] = $versions[$name] ?? throw new RuntimeException('Missing locked runtime root: ' . $name);
}
$manifest = [
    'name' => $source['name'],
    'license' => $source['license'],
    'version' => getenv('COMPOSER_ROOT_VERSION') ?: $sourceIdentity['root']['pretty_version'],
    'require' => $require,
    'autoload' => ['psr-4' => ['FastForward\\CodeStandards\\' => 'src/']],
    'repositories' => [['type' => 'package', 'package' => $packages], ['packagist.org' => false]],
    'config' => ['platform' => $source['config']['platform'], 'allow-plugins' => false],
];
writeJson($runtime . '/composer.json', $manifest);
runCommand(['composer', 'update', '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress', '--no-audit'], $runtime);
$installed = json_decode(file_get_contents($runtime . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
if (false !== $installed['dev']) {
    throw new RuntimeException('Build unexpectedly contains development packages.');
}
$identity = require $runtime . '/vendor/composer/installed.php';
$inventory = [];
foreach ($installed['packages'] as $package) {
    $inventory[$package['name']] = ['version' => $package['version'], 'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null];
}
ksort($inventory);
writeJson($runtime . '/distribution-manifest.json', [
    'root' => array_intersect_key($identity['root'], array_flip(['name', 'version', 'pretty_version', 'reference'])),
    'composer_lock_sha256' => hash_file('sha256', $root . '/composer.lock'),
    'packages' => $inventory,
    'compression' => 'GZ',
]);
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
}
$temporary = $output . '.' . bin2hex(random_bytes(8)) . '.phar';
register_shutdown_function(static function () use ($temporary): void {
    if (is_file($temporary)) {
        unlink($temporary);
    }
});
$archive = new Phar($temporary);
$archive->startBuffering();
$archive->buildFromDirectory($runtime);
$archive->setStub(file_get_contents(__DIR__ . '/stub.php'));
$archive->setSignatureAlgorithm(Phar::SHA256);
$archive->compressFiles(Phar::GZ);
$archive->stopBuffering();
unset($archive);
chmod($temporary, 0755);
rename($temporary, $output);
fwrite(STDOUT, hash_file('sha256', $output) . '  ' . $output . PHP_EOL);
