<?php

declare(strict_types=1);

function installationAvailable(string $vendorDirectory): bool
{
    $engine = realpath($vendorDirectory . '/symplify/easy-coding-standard');
    $entrypoint = $engine . '/bin/ecs';
    if (!str_starts_with($vendorDirectory, '/')
        || false === $engine
        || !is_file($entrypoint)
        || !is_readable($entrypoint)
        || !is_readable($vendorDirectory . '/autoload.php')
    ) {
        return false;
    }

    return true;
}
