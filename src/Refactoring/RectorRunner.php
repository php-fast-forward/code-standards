<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Refactoring;

use FastForward\CodeStandards\Output\OutputFormat;
use FastForward\CodeStandards\Process\ProcessRunner;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/** Runs the installed native CLI; configuration owns rules, target PHP, paths and cache. */
final readonly class RectorRunner
{
    /** @param string $rector Absolute Rector entrypoint in this tool's Composer installation. */
    public function __construct(private ProcessRunner $process, private string $rector) {}

    /**
     * Requires an explicit native PHP target and rules before previewing or explicitly applying.
     *
     * Configuration is executable PHP and must be trusted/deterministic: preview uses inventory and process;
     * successful apply adds verification. Native Rector may evaluate configuration more than once per invocation.
     * This transport is not a filesystem sandbox.
     *
     * JSON is native preview output only; apply plus verification would produce multiple documents.
     * Invalid output/write combinations fail before evaluating executable configuration.
     *
     * @return int Native process status, including 2 when dry-run proposes changes.
     */
    public function run(string $directory, string $config, bool $apply, float $timeout = 300, string $format = OutputFormat::Text->value): int
    {
        $outputFormat = OutputFormat::tryFrom($format);
        if (!in_array(needle: $outputFormat, haystack: [OutputFormat::Text, OutputFormat::Json], strict: true)) {
            throw new InvalidArgumentException('Rector format must be text or json.');
        }
        if ($apply && OutputFormat::Json === $outputFormat) {
            throw new InvalidArgumentException('JSON output is preview-only; use text output for explicit --apply.');
        }

        $this->assertRules($directory, $config, $timeout);
        $check = [PHP_BINARY, $this->rector, 'process', '--config=' . $config, '--no-progress-bar'];
        if (OutputFormat::Json === $outputFormat) {
            $check[] = '--output-format=json';
        }
        $status = $this->process->run($apply ? $check : [...$check, '--dry-run'], $directory, $timeout);
        if (!$apply || 0 !== $status) {
            return $status;
        }

        return $this->process->run([...$check, '--dry-run'], $directory, $timeout);
    }

    /** Uses Rector's own JSON inventory instead of evaluating or parsing consumer PHP in the parent process. */
    private function assertRules(string $directory, string $config, float $timeout): void
    {
        $output = $this->nativeInventory($directory, $config, $timeout);
        try {
            $inventory = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Rector did not return a valid native rule inventory.', previous: $error);
        }
        $rules = is_array($inventory) ? ($inventory['rectors'] ?? null) : null;
        if (
            !is_array($rules)
            || [] === $rules
            || !array_is_list($rules)
            || count(array_filter($rules, static fn(mixed $rule): bool => is_string($rule) && '' !== $rule)) !== count($rules)
        ) {
            throw new InvalidArgumentException('Select a native Rector configuration with at least one rule; automatic initialization is disabled.');
        }
    }

    /** Imports the original config in Rector's native container; no application/config source is copied or parsed. */
    private function nativeInventory(string $directory, string $config, float $timeout): string
    {
        $temporary = sys_get_temp_dir() . '/code-standards-rector-' . bin2hex(random_bytes(16));
        if (!mkdir($temporary, 0700)) {
            throw new RuntimeException('Cannot create the native Rector preflight configuration.');
        }
        $preflight = $temporary . '/preflight.php';
        try {
            $source = $this->targetGuard($config);
            if (strlen($source) !== file_put_contents($preflight, $source)) {
                throw new RuntimeException('Cannot write the native Rector preflight configuration.');
            }

            return $this->process->capture([
                PHP_BINARY, $this->rector, 'list-rules', '--config=' . $preflight, '--output-format=json',
            ], $directory, $timeout);
        } finally {
            if (is_file($preflight)) {
                unlink($preflight);
            }
            rmdir($temporary);
        }
    }

    /** Uses public native configuration/parameter APIs; actual process commands retain the original config identity. */
    private function targetGuard(string $config): string
    {
        return sprintf(<<<'PHP'
            <?php
            use Rector\Config\RectorConfig;
            use Rector\Configuration\Option;
            use Rector\Configuration\Parameter\SimpleParameterProvider;

            return static function (RectorConfig $config): void {
                $implicitTarget = SimpleParameterProvider::hasParameter(Option::PHP_VERSION_FEATURES);
                $config->import(%s);
                if ($implicitTarget
                    || !SimpleParameterProvider::hasParameter(Option::PHP_VERSION_FEATURES)
                    || SimpleParameterProvider::provideIntParameter(Option::PHP_VERSION_FEATURES) <= 0) {
                    throw new InvalidArgumentException('Select an explicit PHP target with native RectorConfig::phpVersion() or withPhpVersion().');
                }
                $config->boot();
                if (!$config->make(\Rector\Configuration\ConfigInitializer::class)->areSomeRectorsLoaded()) {
                    fwrite(STDERR, 'Select a native Rector configuration with at least one rule; automatic initialization is disabled.' . PHP_EOL);
                    exit(2);
                }
            };
            PHP, var_export($config, true));
    }
}
