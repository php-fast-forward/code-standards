<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Copies complete inputs, rejecting nonportable symbolic links. */
function copyTree(string $source, string $target): void
{
    if (is_link($source)) {
        throw new RuntimeException('Symbolic build input: ' . $source);
    }
    if (is_file($source)) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        copy($source, $target);

        return;
    }
    mkdir($target, 0755, true);
    foreach (new DirectoryIterator($source) as $file) {
        if (!$file->isDot()) {
            copyTree($file->getPathname(), $target . '/' . $file->getFilename());
        }
    }
}

/** Removes an exclusively owned tree without following links. */
function removeTree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_dir($path) && !is_link($path)) {
        foreach (new DirectoryIterator($path) as $file) {
            if (!$file->isDot()) {
                removeTree($file->getPathname());
            }
        }
        rmdir($path);

        return;
    }
    unlink($path);
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
        register_shutdown_function(static fn () => removeTree($home));
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
