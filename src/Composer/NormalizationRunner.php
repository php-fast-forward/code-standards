<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Composer;

use Composer\Package\Locker;
use Ergebnis\Json\Json;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/** Owns explicit manifest writes; never constructs a Composer application, plugin manager or installer. */
final readonly class NormalizationRunner
{
    /** Injects the in-memory normalizer and invocation output boundary. */
    public function __construct(private MetadataNormalizer $normalizer, private OutputInterface $output) {}

    /**
     * Returns 0 when normalized and lock-fresh, or 1 for proposed changes/stale locks.
     * Apply writes only composer.json and can leave a stale lock, reported after the write.
     *
     * @throws RuntimeException When files cannot be read/written or JSON/schema validation fails.
     */
    public function run(string $directory, bool $apply): int
    {
        $manifest = $directory . '/composer.json';
        $source = $this->read($manifest);
        $normalized = $this->normalizer->normalize($source);
        $lock = $directory . '/composer.lock';
        $lockData = null;
        if (file_exists($lock) || is_link($lock)) {
            $decoded = Json::fromString($this->read($lock))->decoded();
            if (!is_object($decoded)) {
                throw new RuntimeException('Expected a Composer lock object.');
            }

            $lockData = (array) $decoded;
            if (!$this->fresh($source, $lockData)) {
                $this->output->writeln('composer.lock is not fresh; review it separately before normalization. No files changed.');

                return 1;
            }
        }

        if ($source === $normalized) {
            $this->output->writeln('composer.json is normalized.');

            return 0;
        }
        if (!$apply) {
            $this->output->writeln('composer.json needs normalization. Inspect changes with normalize-composer --apply and git diff.');

            return 1;
        }
        if (strlen($normalized) !== file_put_contents($manifest, $normalized)) {
            throw new RuntimeException('Cannot write composer.json; inspect the local diff for partial changes.');
        }

        $this->output->writeln('Normalized composer.json. Inspect its diff. composer.lock was not changed.');
        if (null !== $lockData && !$this->fresh($normalized, $lockData)) {
            $this->output->writeln('composer.lock content-hash is incompatible after normalization; review lock maintenance separately.');

            return 1;
        }

        return 0;
    }

    /** Rejects symlinks rather than reading or overwriting another project's metadata. */
    private function read(string $path): string
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Expected a readable regular metadata file: ' . $path);
        }

        $bytes = file_get_contents($path);
        if (false === $bytes) {
            throw new RuntimeException('Cannot read metadata file: ' . $path);
        }

        return $bytes;
    }

    /** @param array<string, mixed> $lock Uses Composer's public hash API, with no application/bootstrap execution. */
    private function fresh(string $source, array $lock): bool
    {
        if (!empty($lock['content-hash'])) {
            return Locker::getContentHash($source) === $lock['content-hash'];
        }

        return !empty($lock['hash']) && md5($source) === $lock['hash'];
    }
}
