#!/usr/bin/env php
<?php

declare(strict_types=1);

// Materialize an exclusive runtime for filesystem-only tool entrypoints and workers.
// No shared cache, dependency resolution, consumer autoload discovery or source build.
$runtime = sys_get_temp_dir() . '/code-standards-phar-' . bin2hex(random_bytes(16));
if (!mkdir($runtime, 0700)) {
    fwrite(STDERR, "Could not create the PHAR runtime directory.\n");
    exit(1);
}
register_shutdown_function(static function () use ($runtime): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($runtime, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($runtime);
});
(new Phar(__FILE__))->extractTo($runtime);
$_composer_autoload_path = $runtime . '/vendor/autoload.php';
require $runtime . '/bin/code-standards';
__HALT_COMPILER();
