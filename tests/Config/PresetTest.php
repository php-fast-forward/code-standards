<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Config;

use FastForward\CodeStandards\Sniff\MultilineBooleanConditionSniff;
use PHP_CodeSniffer\Sniffs\Sniff;
use PhpCsFixer\Fixer\FixerInterface;
use PHPUnit\Framework\TestCase;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\DependencyInjection\ServiceContainerFactory;

/** Captures the actual compiled preset, including upstream configuration and risky-rule classification. */
final class PresetTest extends TestCase
{
    /** Dependency upgrades must expose effective rule changes for deliberate review before updating the snapshot. */
    public function testCompiledPresetMatchesReviewedRules(): void
    {
        $vendor = dirname(__DIR__, 2) . '/vendor';
        $packageRoot = realpath($vendor . '/symplify/easy-coding-standard');
        $installed = json_decode(file_get_contents($vendor . '/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
        $package = array_values(array_filter($installed['packages'], static fn(array $entry): bool => 'symplify/easy-coding-standard' === $entry['name']))[0];

        require_once $packageRoot . '/vendor/squizlabs/php_codesniffer/autoload.php';

        $container = (new ServiceContainerFactory())->create([dirname(__DIR__, 2) . '/config/ecs.php']);
        $container->boot();
        $configuration = $container->getCheckerConfiguration();
        $rules = $this->fixerRules($container, $configuration, $packageRoot) + $this->sniffRules($container, $configuration);

        ksort($rules);
        $expected = json_decode(file_get_contents(__DIR__ . '/../Fixtures/preset-rules.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($expected, [
            'ecs_version' => $package['version'],
            'ecs_reference' => $package['source']['reference'],
            'rules' => $rules,
        ]);
        self::assertCount(count($container->getCheckerClasses()), $rules, 'Every active checker must appear in this snapshot.');
    }

    /**
     * @param array<class-string, mixed[]> $configuration Effective native options after skip/boot passes.
     * @return array<string, array<string, mixed>> Native risky classification remains mandatory for every fixer.
     */
    private function fixerRules(ECSConfig $container, array $configuration, string $packageRoot): array
    {
        $rules = [];
        foreach ($container->findByContract(FixerInterface::class) as $fixer) {
            $rules[$fixer->getName()] = [
                'type' => 'fixer',
                'class' => $fixer::class,
                'configuration' => $configuration[$fixer::class],
                'risky' => $fixer->isRisky(),
            ];
            self::assertFalse($fixer->isRisky(), $fixer->getName() . ' must not become implicitly risky.');
            self::assertStringStartsWith($packageRoot . '/', (new \ReflectionClass($fixer))->getFileName());
        }

        return $rules;
    }

    /**
     * @param array<class-string, mixed[]> $configuration Effective options, including the absence of owned scalar options.
     * @return array<string, array<string, mixed>> PHPCS has no native isRisky method; only this behaviorally tested class is allowed.
     */
    private function sniffRules(ECSConfig $container, array $configuration): array
    {
        $sniffs = array_values($container->findByContract(Sniff::class));
        self::assertSame([MultilineBooleanConditionSniff::class], array_map(static fn(Sniff $sniff): string => $sniff::class, $sniffs), 'Unknown default Sniffs require an explicit policy review.');
        self::assertSame(realpath(dirname(__DIR__, 2) . '/src/Sniff/MultilineBooleanConditionSniff.php'), (new \ReflectionClass($sniffs[0]))->getFileName());

        return [MultilineBooleanConditionSniff::class => [
            'type' => 'sniff',
            'class' => MultilineBooleanConditionSniff::class,
            'configuration' => $configuration[MultilineBooleanConditionSniff::class],
            'risky' => null,
            'risk_policy' => 'owned-whitespace-only',
            'source' => 'src/Sniff/MultilineBooleanConditionSniff.php',
        ]];
    }
}
