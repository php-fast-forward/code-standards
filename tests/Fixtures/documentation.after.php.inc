<?php

declare(strict_types=1);

namespace Fixture;

/**
 * Descriptive API contract.
 *
 * @package Fixture
 * @subpackage Documentation
 * @author Fixture Maintainer
 * @final Keep this described marker for downstream documentation.
 * @phpstan-type Payload array{items: list<string>}
 * @psalm-type Payload array{items: list<string>}
 */
final class DescribedContract
{
    /**
     * @inheritDoc
     *
     * @param string $value Input description.
     * @param list<string> $items
     * @param array{key: string} $options Rich shape contract.
     * @param string $virtual Parameter handled by an external proxy.
     * @return string|null A value when available, otherwise no result.
     */
    public function describe(string $value, array $items, array $options): ?string
    {
        return $items[0] ?? $value;
    }

    /**
     * Prefix {@inheritDoc} Keep this local explanation after the inherited text.
     *
     * @param string $value
     * @return void This notifies the external system; no response is returned.
     */
    public function notify(string $value): void
    {
        echo $value;
    }

    /**
     * @return null Nothing is returned when no mapping exists.
     */
    public function absent(): null
    {
        return null;
    }

    /**
     * @param string $value Multi-line description:
     *                      preserve this continuation.
     * @return class-string A class name rather than an arbitrary string.
     */
    public function className(string $value): string
    {
        /** @var class-string $value Keep the analyzer assertion before return. */
        return $value;
    }
}

/**
 * @abstract Preserve this described inheritance constraint.
 */
abstract class DescribedBase {}
