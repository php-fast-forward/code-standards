<?php

declare(strict_types=1);

/** @phpstan-type Entry array{id: positive-int, label: non-empty-string} */
final readonly class Catalog
{
    public const string LABEL = 'catalog';

    /** @return list<Entry> */
    public function entries(): array
    {
        return array(array('id' => 1, 'label' => self::LABEL));
    }
}
