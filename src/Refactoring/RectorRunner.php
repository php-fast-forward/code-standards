<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Refactoring;

use FastForward\CodeStandards\Process\ProcessRunner;
use InvalidArgumentException;
use JsonException;

/** Runs the installed native CLI; configuration owns rules, target PHP, paths and cache. */
final readonly class RectorRunner
{
    /** @param string $rector Absolute Rector entrypoint in this tool's Composer installation. */
    public function __construct(private ProcessRunner $process, private string $rector) {}

    /**
     * Rejects an empty rule set before Rector can initialize configuration, then previews or explicitly applies.
     *
     * Configuration is executable PHP and must be trusted/deterministic: preview evaluates it twice;
     * apply with verification evaluates it three times. This transport is not a filesystem sandbox.
     *
     * @return int Native process status, including 2 when dry-run proposes changes.
     */
    public function run(string $directory, string $config, bool $apply): int
    {
        $this->assertRules($directory, $config);
        $check = [PHP_BINARY, $this->rector, 'process', '--config=' . $config, '--no-progress-bar'];
        $status = $this->process->run($apply ? $check : [...$check, '--dry-run'], $directory);
        if (!$apply || 0 !== $status) {
            return $status;
        }

        return $this->process->run([...$check, '--dry-run'], $directory);
    }

    /** Uses Rector's own JSON inventory instead of evaluating or parsing consumer PHP in the parent process. */
    private function assertRules(string $directory, string $config): void
    {
        $output = $this->process->capture([
            PHP_BINARY, $this->rector, 'list-rules', '--config=' . $config, '--output-format=json',
        ], $directory);
        try {
            $inventory = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Rector did not return a valid native rule inventory.', previous: $error);
        }
        $rules = is_array($inventory) ? ($inventory['rectors'] ?? null) : null;
        if (!is_array($rules) || [] === $rules || !array_is_list($rules)
            || count(array_filter($rules, static fn(mixed $rule): bool => is_string($rule) && '' !== $rule)) !== count($rules)) {
            throw new InvalidArgumentException('Select a native Rector configuration with at least one rule; automatic initialization is disabled.');
        }
    }
}
