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
        // Upstream mutates decoded objects; isolate policy from its normalization input.
        $policy = Json::fromString($source)->decoded();
        $normalized = (new ComposerJsonNormalizer('file://' . $this->schema))->normalize($original)->decoded();
        $this->restoreDependencies($policy, $normalized);
        $this->restorePriorities($policy, $normalized);

        $encoded = json_encode($normalized, $format->jsonEncodeOptions()->toInt() | JSON_THROW_ON_ERROR);
        $printed = (new Printer())->print($encoded, $format->indent()->toString(), $format->newLine()->toString());

        return $printed . ($format->hasFinalNewLine() ? $format->newLine()->toString() : '');
    }

    /** Retains aliases/collisions in their original order whenever upstream would canonicalize package identities. */
    private function restoreDependencies(object $original, object $normalized): void
    {
        foreach (['require', 'require-dev', 'conflict', 'provide', 'replace', 'suggest'] as $section) {
            if (!isset($original->{$section})) {
                continue;
            }

            $packages = (array) $original->{$section};
            $normalizedPackages = (array) ($normalized->{$section} ?? []);
            $keysChanged = [] !== array_diff_key($packages, $normalizedPackages) || [] !== array_diff_key($normalizedPackages, $packages);
            $caseCollision = count(array_change_key_case($packages, CASE_LOWER)) !== count($packages);
            if ($keysChanged || $caseCollision) {
                $normalized->{$section} = (object) $packages;

                continue;
            }

            foreach ($packages as $package => $value) {
                $normalized->{$section}->{$package} = $value;
            }
        }
    }

    /** First-match rule maps must retain their original order, including intentionally broad-first deny policies. */
    private function restorePriorities(object $original, object $normalized): void
    {
        if (!isset($original->config)) {
            return;
        }

        foreach (['allow-plugins', 'preferred-install'] as $property) {
            if (!property_exists($original->config, $property)) {
                continue;
            }

            $normalized->config ??= new \stdClass();
            $normalized->config->{$property} = $original->config->{$property};
        }
    }
}
