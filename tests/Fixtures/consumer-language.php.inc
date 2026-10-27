<?php

function example(
    int $count,
    ?string $name = null
): array {
    return [
        'array value'
    ];
}

function existingComma(
    string $name,
): void {
    consume(
        'argument value'
    );
}

function typedOnly(
    int $count,
    string $label
): void {
}

(new Example())->run();
(new class() {})->run();
