<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Output;

/** Identifies public presentation choices; each command retains its supported subset and native schema. */
enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case JsonPretty = 'json-pretty';

    /** @return list<string> Public presentation values derived from the declared cases in order. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
