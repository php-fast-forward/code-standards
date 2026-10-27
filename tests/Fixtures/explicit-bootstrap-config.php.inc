<?php

declare(strict_types=1);

require 'vendor/autoload.php';

$metadata = json_decode(file_get_contents('composer.json'), true, flags: JSON_THROW_ON_ERROR);
$nativeSource = (new ReflectionClass(Symplify\EasyCodingStandard\DependencyInjection\EasyCodingStandardContainerFactory::class))->getFileName();

file_put_contents(__DIR__ . '/context.json', json_encode([
    'cwd' => getcwd(),
    'config_directory' => __DIR__,
    'relative' => is_file('relative-marker'),
    'absolute' => is_file(dirname(__DIR__) . '/relative-marker'),
    'editorconfig' => is_file('.editorconfig'),
    'package' => $metadata['name'],
    'metadata_changed' => Composer\InstalledVersions::getInstallPath('symplify/easy-coding-standard') === __DIR__ . '/nonexistent-engine',
    'native_source' => $nativeSource,
], JSON_THROW_ON_ERROR));

return FastForward\CodeStandards\Config\ECSConfig::configure(
    static fn(Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder $config) => $config->withRules([CodeStandardsFixture\BootstrapFixer::class]),
);
