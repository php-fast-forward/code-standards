<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Config;

use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer;
use RuntimeException;
use Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder;

/** A deliberately non-risky PER Coding Style 3.0 preset for extraction. */
final class ECSConfig
{
    /**
     * Returns native ECS defaults with explicit consumer overrides applied last.
     *
     * @param callable(ECSConfigBuilder):void|null $customize Native rule imports, skips or overrides; null uses the preset unchanged.
     * @return ECSConfigBuilder The native builder, ready to return from an explicit ECS configuration file.
     * @throws RuntimeException When the executing checkout cannot be identified for cache isolation.
     */
    public static function configure(?callable $customize = null): ECSConfigBuilder
    {
        $config = (new ECSConfigBuilder())
            ->withPreparedSets(perCs: true)
            ->withSkip([
                NoSuperfluousPhpdocTagsFixer::class,
                NoEmptyPhpdocFixer::class,
                PhpdocNoEmptyReturnFixer::class,
                GeneralPhpdocAnnotationRemoveFixer::class,
            ])
            ->withConfiguredRule(MethodArgumentSpaceFixer::class, ['on_multiline' => 'ignore'])
            ->withConfiguredRule(PhpdocAlignFixer::class, ['align' => 'left'])
            ->withCache(self::cacheDirectory())
            ->withoutParallel();
        if (null !== $customize) {
            $customize($config);
        }

        return $config;
    }

    /** Keeps ECS invalidation within the executing checkout's temporary cache; native overrides still run last. */
    private static function cacheDirectory(): string
    {
        $directory = getcwd();
        if (false === $directory) {
            throw new RuntimeException('Cannot determine the checkout directory for the ECS cache.');
        }

        return sys_get_temp_dir() . '/fast-forward-code-standards/' . hash('sha256', $directory);
    }
}
