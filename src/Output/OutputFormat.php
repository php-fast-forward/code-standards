<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Output;

/** Identifies public presentation choices; each command retains its supported subset and native schema. */
enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case JsonPretty = 'json-pretty';

    /** Public presentation values usable in attribute constant expressions. */
    public const VALUES = [self::Text->value, self::Json->value, self::JsonPretty->value];
}
