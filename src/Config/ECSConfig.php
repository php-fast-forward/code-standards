<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Config;

use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer;
use Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder;

/** A deliberately non-risky PSR-12 starting point for extraction. */
final class ECSConfig
{
    /**
     * Returns native ECS defaults with explicit consumer overrides applied last.
     *
     * @param callable(ECSConfigBuilder):void|null $customize Native rule imports, skips or overrides; null uses the preset unchanged.
     * @return ECSConfigBuilder The native builder, ready to return from an explicit ECS configuration file.
     */
    public static function configure(?callable $customize = null): ECSConfigBuilder
    {
        $config = (new ECSConfigBuilder())
            ->withPreparedSets(psr12: true)
            ->withSkip([
                PhpdocToCommentFixer::class,
                NoSuperfluousPhpdocTagsFixer::class,
                NoEmptyPhpdocFixer::class,
                PhpdocNoEmptyReturnFixer::class,
                GlobalNamespaceImportFixer::class,
                GeneralPhpdocAnnotationRemoveFixer::class,
            ])
            ->withConfiguredRule(PhpdocAlignFixer::class, ['align' => 'left'])
            ->withoutParallel();
        if (null !== $customize) {
            $customize($config);
        }

        return $config;
    }
}
