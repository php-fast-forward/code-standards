<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Config;

use PhpCsFixer\Fixer\FixerInterface;
use PHPUnit\Framework\TestCase;
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
        $package = array_values(array_filter($installed['packages'], static fn (array $entry): bool => 'symplify/easy-coding-standard' === $entry['name']))[0];

        require_once $packageRoot . '/vendor/squizlabs/php_codesniffer/autoload.php';

        $container = (new ServiceContainerFactory())->create([dirname(__DIR__, 2) . '/config/ecs.php']);
        $container->boot();
        $configuration = $container->getCheckerConfiguration();
        $rules = [];

        foreach ($container->findByContract(FixerInterface::class) as $fixer) {
            $rules[$fixer->getName()] = [
                'class' => $fixer::class,
                'configuration' => $configuration[$fixer::class],
                'risky' => $fixer->isRisky(),
            ];
            self::assertFalse($fixer->isRisky(), $fixer->getName() . ' must not become implicitly risky.');
            self::assertStringStartsWith($packageRoot . '/', (new \ReflectionClass($fixer))->getFileName());
        }

        ksort($rules);
        $expected = json_decode(file_get_contents(__DIR__ . '/../Fixtures/preset-rules.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($expected, [
            'ecs_version' => $package['version'],
            'ecs_reference' => $package['source']['reference'],
            'rules' => $rules,
        ]);
        self::assertCount(count($container->getCheckerClasses()), $rules, 'Every active checker must appear in this snapshot.');
    }
}
