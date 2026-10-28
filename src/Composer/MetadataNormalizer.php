<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Composer;

use Ergebnis\Json\Json;
use Ergebnis\Json\Normalizer\Format\Format;
use Ergebnis\Json\Normalizer\Vendor\Composer\ComposerJsonNormalizer;
use Ergebnis\Json\Printer\Printer;

/** Reuses composer-normalize's library while retaining the consumer's literal dependency policy. */
final readonly class MetadataNormalizer
{
    /** @param string $schema Absolute local Composer schema from the tool installation; never a consumer URL. */
    public function __construct(private string $schema) {}

    /** Normalizes metadata in memory, preserving sniffed formatting and all original constraint strings. */
    public function normalize(string $source): string
    {
        $original = Json::fromString($source);
        $format = Format::fromJson($original);
        $constraints = [];
        foreach (['require', 'require-dev', 'conflict', 'provide', 'replace'] as $section) {
            if (is_object($original->decoded()) && isset($original->decoded()->{$section})) {
                $constraints[$section] = (array) $original->decoded()->{$section};
            }
        }

        $normalized = (new ComposerJsonNormalizer('file://' . $this->schema))->normalize($original)->decoded();

        foreach ($constraints as $section => $packages) {
            foreach ($packages as $package => $constraint) {
                $normalized->{$section}->{$package} = $constraint;
            }
        }

        $encoded = json_encode($normalized, $format->jsonEncodeOptions()->toInt() | JSON_THROW_ON_ERROR);
        $printed = (new Printer())->print($encoded, $format->indent()->toString(), $format->newLine()->toString());

        return $printed . ($format->hasFinalNewLine() ? $format->newLine()->toString() : '');
    }
}
