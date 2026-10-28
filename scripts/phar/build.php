<?php

declare(strict_types=1);

// Experimental packer. Input is a disposable, Composer-installed --no-dev runtime tree.
// It never updates the working checkout, resolves packages or invokes this tool itself.
if (!in_array($argc, [3, 4], true) || !is_dir($argv[1]) || !str_ends_with($argv[2], '.phar') || (4 === $argc && '--gzip' !== $argv[3])) {
    fwrite(STDERR, "Usage: php -d phar.readonly=0 scripts/phar/build.php RUNTIME_DIRECTORY OUTPUT.phar [--gzip]\n");
    exit(2);
}
$runtime = realpath($argv[1]);
$output = $argv[2];
$gzip = '--gzip' === ($argv[3] ?? null);
foreach (['src', 'config', 'resources', 'vendor', 'bin/code-standards', 'bin/ecs-worker', 'composer.json', 'composer.lock', 'LICENSE'] as $path) {
    if (!file_exists($runtime . '/' . $path)) {
        throw new RuntimeException('Incomplete runtime tree: ' . $path);
    }
}
$installed = json_decode(file_get_contents($runtime . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
if (false !== ($installed['dev'] ?? null)) {
    throw new RuntimeException('Stage dependencies must be installed with --no-dev.');
}
if (file_exists($output) || str_starts_with(realpath(dirname($output)) . '/', $runtime . '/')) {
    throw new RuntimeException('Choose a new output outside the runtime tree.');
}
$archive = new Phar($output);
$archive->startBuffering();
$archive->buildFromDirectory($runtime);
$identity = require $runtime . '/vendor/composer/installed.php';
$packages = [];
foreach ($installed['packages'] as $package) {
    $packages[$package['name']] = [
        'version' => $package['version'],
        'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
    ];
}
ksort($packages);
$archive['distribution-manifest.json'] = json_encode([
    'root' => array_intersect_key($identity['root'], array_flip(['name', 'version', 'pretty_version', 'reference'])),
    'composer_lock_sha256' => hash_file('sha256', $runtime . '/composer.lock'),
    'packages' => $packages,
    'compression' => $gzip ? 'GZ' : 'none',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$archive->setStub(file_get_contents(__DIR__ . '/stub.php'));
$archive->setSignatureAlgorithm(Phar::SHA256);
if ($gzip) {
    $archive->compressFiles(Phar::GZ);
}
$archive->stopBuffering();
chmod($output, 0755);
fwrite(STDOUT, hash_file('sha256', $output) . '  ' . $output . PHP_EOL);
