<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Copies complete inputs, rejecting nonportable symbolic links. */
function copyTree(string $source, string $target): void
{
    if (is_link($source)) {
        throw new RuntimeException('Symbolic build input: ' . $source);
    }
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    if (is_dir($source)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symbolic build input: ' . $file->getPathname());
            }
        }
        $filesystem->mirror($source, $target);

        return;
    }
    $filesystem->copy($source, $target, true);
}

/** Removes an exclusively owned tree without following links. */
function removeTree(string $path): void
{
    (new Symfony\Component\Filesystem\Filesystem())->remove($path);
}

/** @param array<mixed> $value */
function writeJson(string $path, array $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}

/** @return array<string, string> Maintained inputs bound to the compiled payload. */
function inputHashes(string $root): array
{
    $hashes = ['LICENSE' => hash_file('sha256', $root . '/LICENSE')];
    foreach (['src', 'config', 'bin', 'resources'] as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $hashes[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }
    }
    ksort($hashes);

    return $hashes;
}

/** @return array<string, string> The PHP recipe that produced the executable. */
function buildHashes(string $root): array
{
    $hashes = [];
    foreach (['build.php', 'stub.php', 'support.php'] as $file) {
        $path = 'scripts/phar/' . $file;
        $hashes[$path] = hash_file('sha256', $root . '/' . $path);
    }
    $hashes['box.json'] = hash_file('sha256', $root . '/box.json');

    return $hashes;
}

/**
 * @param list<string> $arguments
 * @param array<string, string|false> $environment Explicit isolated overrides.
 */
function runCommand(array $arguments, string $directory, int $expected = 0, array $environment = []): string
{
    static $home = null;
    if (null === $home) {
        $home = sys_get_temp_dir() . '/standards-composer-' . bin2hex(random_bytes(12));
        mkdir($home, 0700);
        register_shutdown_function(static fn() => removeTree($home));
    }
    $isolated = [];
    foreach (array_keys(getenv()) as $name) {
        if (str_starts_with($name, 'COMPOSER') || str_starts_with($name, 'GIT_')) {
            $isolated[$name] = false;
        }
    }
    $isolated = array_replace($isolated, [
        'COMPOSER_HOME' => $home,
        'COMPOSER_CACHE_DIR' => $home . '/cache',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_SYSTEM' => '/dev/null',
        'GIT_TERMINAL_PROMPT' => '0',
    ], $environment);
    $process = new Symfony\Component\Process\Process($arguments, $directory, $isolated, timeout: 300);
    $process->run();
    if ($expected !== $process->getExitCode()) {
        throw new RuntimeException('Command failed: ' . implode(' ', $arguments) . PHP_EOL . $process->getOutput() . $process->getErrorOutput());
    }

    return $process->getOutput();
}
