<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Config;

use FastForward\CodeStandards\Sniff\MultilineBooleanConditionSniff;
use FastForward\CodeStandards\Sniff\MultilineConditionSpacingSniff;
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
     * @throws RuntimeException When the checkout or owned policy source cannot be identified for cache isolation.
     */
    public static function configure(?callable $customize = null): ECSConfigBuilder
    {
        $config = (new ECSConfigBuilder())
            ->withPreparedSets(perCs: true)
            ->withRules([MultilineBooleanConditionSniff::class, MultilineConditionSpacingSniff::class])
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

    /** Keeps each checkout and owned policy revision in its own temporary cache; native overrides still run last. */
    private static function cacheDirectory(): string
    {
        $directory = getcwd();
        if (false === $directory) {
            throw new RuntimeException('Cannot determine the checkout directory for the ECS cache.');
        }

        return sys_get_temp_dir() . '/fast-forward-code-standards/' . hash('sha256', $directory) . '/' . self::policyFingerprint();
    }

    /** Includes both source bytes and installation paths so an unchanged consumer cannot retain an older owned rule result. */
    private static function policyFingerprint(): string
    {
        $paths = [
            __FILE__,
            dirname(__DIR__) . '/Sniff/MultilineBooleanConditionSniff.php',
            dirname(__DIR__) . '/Sniff/MultilineConditionSpacingSniff.php',
        ];
        $sources = [];
        foreach ($paths as $path) {
            $hash = hash_file('sha256', $path);
            if (false === $hash) {
                throw new RuntimeException('Cannot fingerprint formatter policy source: ' . $path);
            }

            $sources[$path] = $hash;
        }

        return hash('sha256', serialize($sources));
    }
}
