<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Workflow;

use InvalidArgumentException;

/** Validates only the explicit reusable inputs; it neither discovers configuration nor enables tools implicitly. */
final readonly class WorkflowInput
{
    /** Immutable event refs and consumer-relative Rector configuration retain their separate scope contracts. */
    public function __construct(
        public string $directory,
        public string $base,
        public string $head,
        public bool $magicNumbers,
        public string $magicNumbersPolicy,
        public bool $rector,
        public string $rectorPolicy,
        public string $rectorConfig,
    ) {
        if (
            !str_starts_with($directory, '/')
            || 1 !== preg_match('/\A[a-fA-F0-9]{40}\z/D', $base)
            || 1 !== preg_match('/\A[a-fA-F0-9]{40}\z/D', $head)
        ) {
            throw new InvalidArgumentException('Optional analyses require an absolute consumer directory and exact base/head SHAs.');
        }
        foreach ([$magicNumbersPolicy, $rectorPolicy] as $policy) {
            if (!in_array($policy, ['report', 'fail'], true)) {
                throw new InvalidArgumentException('Optional findings policy must be report or fail.');
            }
        }
    }

    /** @param array<string, string> $environment Literal environment fields supplied by the composite action. */
    public static function fromEnvironment(array $environment): self
    {
        return new self(
            self::field($environment, 'STANDARDS_DIRECTORY'),
            self::field($environment, 'STANDARDS_BASE'),
            self::field($environment, 'STANDARDS_HEAD'),
            self::boolean(self::field($environment, 'STANDARDS_MAGIC_NUMBERS')),
            self::field($environment, 'STANDARDS_MAGIC_NUMBERS_POLICY'),
            self::boolean(self::field($environment, 'STANDARDS_RECTOR')),
            self::field($environment, 'STANDARDS_RECTOR_POLICY'),
            self::field($environment, 'STANDARDS_RECTOR_CONFIG'),
        );
    }

    /** Missing fields fail explicitly rather than becoming a silently disabled analysis. */
    private static function field(array $environment, string $name): string
    {
        if (!isset($environment[$name]) || !is_string($environment[$name])) {
            throw new InvalidArgumentException('Missing optional-analysis input: ' . $name);
        }

        return $environment[$name];
    }

    /** GitHub booleans have a literal true/false representation; other strings are not coerced. */
    private static function boolean(string $value): bool
    {
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException('Optional analysis enable inputs must be true or false.');
        }

        return 'true' === $value;
    }
}
