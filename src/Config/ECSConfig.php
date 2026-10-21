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
    /** Consumer callbacks run last and may import local rules explicitly. */
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
