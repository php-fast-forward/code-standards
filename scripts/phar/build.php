<?php

declare(strict_types=1);

// Resolve exact locked runtime roots in a disposable tree, never the checkout.
require __DIR__ . '/support.php';

$root = dirname(__DIR__, 2);
$output = $argv[1] ?? $root . '/dist/code-standards';
if ($argc > 2 || ($argc > 1 && file_exists($output))) {
    throw new RuntimeException('Usage: php scripts/phar/build.php [NEW_OUTPUT]');
}
$recipe = buildHashes($root);
$runtime = sys_get_temp_dir() . '/standards-build-' . bin2hex(random_bytes(12));
mkdir($runtime, 0700);
register_shutdown_function(static fn() => removeTree($runtime));
foreach (['src', 'config', 'resources', 'bin', 'LICENSE'] as $path) {
    copyTree($root . '/' . $path, $runtime . '/' . $path);
}
$sourceContent = file_get_contents($root . '/composer.json');
$source = json_decode($sourceContent, true, flags: JSON_THROW_ON_ERROR);
$lockContent = file_get_contents($root . '/composer.lock');
$lock = json_decode($lockContent, true, flags: JSON_THROW_ON_ERROR);
$packages = array_merge($lock['packages'], $lock['packages-dev']);
$versions = array_column($packages, 'version', 'name');
$require = $source['require'];
foreach ($source['extra']['phar']['runtime-packages'] as $name) {
    $require[$name] = $versions[$name] ?? throw new RuntimeException('Missing locked runtime root: ' . $name);
}
$manifest = [
    'name' => $source['name'],
    'license' => $source['license'],
    'version' => $source['version'] ?? 'dev-main',
    'require' => $require,
    'autoload' => ['psr-4' => ['FastForward\\CodeStandards\\' => 'src/']],
    'repositories' => [['type' => 'package', 'package' => $packages], ['packagist.org' => false]],
    'config' => ['platform' => $source['config']['platform'], 'allow-plugins' => false],
];
writeJson($runtime . '/composer.json', $manifest);
runCommand([PHP_BINARY, $root . '/vendor/composer/composer/bin/composer', 'update', '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress', '--no-audit'], $runtime);
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
    'composer_lock_sha256' => hash('sha256', $lockContent),
    'composer_json_sha256' => hash('sha256', $sourceContent),
    'source_files_sha256' => inputHashes($runtime),
    'build_recipe_sha256' => $recipe,
    'packages' => $inventory,
    'compression' => json_decode(file_get_contents($root . '/box.json'), true, flags: JSON_THROW_ON_ERROR)['compression'],
]);
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
}
copy($root . '/box.json', $runtime . '/box.json');
copy(__DIR__ . '/stub.php', $runtime . '/stub.php');
runCommand(['box', 'compile', '--no-parallel', '--sort-compiled-files', '--config=box.json'], $runtime);
$filesystem = new Symfony\Component\Filesystem\Filesystem();
$staging = $filesystem->tempnam(dirname($output), '.code-standards-');
try {
    $filesystem->copy($runtime . '/dist/code-standards', $staging, true);
    $filesystem->chmod($staging, fileperms($runtime . '/dist/code-standards') & 0777);
    $filesystem->rename($staging, $output, true);
} finally {
    $filesystem->remove($staging);
}
fwrite(STDOUT, hash_file('sha256', $output) . '  ' . $output . PHP_EOL);
