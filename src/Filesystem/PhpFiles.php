<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Filesystem;

/** Applies the owned PHP scope before invoking tools. */
final class PhpFiles
{
    public const EXCLUDED = ['vendor', '.git', '.build', '.dev-tools', 'backup', 'cache', 'public', 'resources', 'tmp', 'node_modules'];

    /**
     * Rejects escaping/symlink paths and preserves spaces and control bytes as arguments.
     *
     * @param list<string> $paths
     * @return list<string>
     */
    public function filter(string $directory, array $paths): array
    {
        $root = realpath($directory);
        if (false === $root) {
            throw new \InvalidArgumentException('Project directory does not exist.');
        }
        $files = [];
        foreach ($paths as $path) {
            if (str_starts_with($path, '/') || str_contains($path, "\0") || in_array('..', explode('/', $path), true)) {
                throw new \InvalidArgumentException('File paths must remain within the project.');
            }
            $segments = explode('/', $path);
            if ([] !== array_intersect($segments, self::EXCLUDED)) {
                continue;
            }
            $current = $root;
            foreach ($segments as $segment) {
                $current .= '/' . $segment;
                if (is_link($current)) {
                    throw new \InvalidArgumentException('Symlink paths are outside the supported PHP scope.');
                }
            }
            if (!is_file($current)) {
                continue;
            }
            $resolved = realpath($current);
            if (false === $resolved || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
                throw new \InvalidArgumentException('File resolves outside the project.');
            }
            if (str_ends_with($path, '.php') || (str_starts_with($path, 'bin/') && str_contains((string) file_get_contents($current, length: 256), '<?php'))) {
                $files[] = $resolved;
            }
        }
        sort($files, SORT_STRING);

        return array_values(array_unique($files));
    }
}
