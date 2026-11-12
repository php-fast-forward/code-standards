<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (2 !== $argc || file_exists($argv[1])) {
    throw new RuntimeException('Usage: php scripts/phar/prepare.php NEW_RUNTIME_DIRECTORY');
}
$root = dirname(__DIR__, 2);
$runtime = $argv[1];
$filesystem = new Filesystem();
$filesystem->mkdir($runtime, 0700);
foreach (['src', 'config', 'resources', 'bin'] as $path) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
        if ($file->isLink()) {
            throw new RuntimeException('Symbolic build input: ' . $file->getPathname());
        }
    }
    $filesystem->mirror($root . '/' . $path, $runtime . '/' . $path);
}
foreach (['LICENSE', 'box.json'] as $path) {
    $filesystem->copy($root . '/' . $path, $runtime . '/' . $path);
}
$filesystem->copy(__DIR__ . '/stub.php', $runtime . '/stub.php');
$source = json_decode(file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
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
    'version' => $source['version'] ?? 'dev-main',
    'require' => $require,
    'autoload' => ['psr-4' => ['FastForward\\CodeStandards\\' => 'src/']],
    'repositories' => [['type' => 'package', 'package' => $packages], ['packagist.org' => false]],
    'config' => ['platform' => $source['config']['platform'], 'allow-plugins' => false],
];
$filesystem->dumpFile($runtime . '/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$process = new Process([
    PHP_BINARY, $root . '/vendor/composer/composer/bin/composer', 'update',
    '--no-dev', '--no-plugins', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress', '--no-audit',
], $runtime, ['COMPOSER' => false, 'COMPOSER_ROOT_VERSION' => false, 'COMPOSER_VENDOR_DIR' => false], timeout: 300);
$process->mustRun();
