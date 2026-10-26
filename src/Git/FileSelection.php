<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Git;

use FastForward\CodeStandards\Process\ProcessRunner;

/** Selects tracked destinations using the PR merge base and exact head. */
final class FileSelection
{
    /** Injects the Git process boundary. */
    public function __construct(private readonly ProcessRunner $process) {}

    /**
     * Returns NUL-safe paths; config or dependency changes require a full scan.
     *
     * @return list<string>
     */
    public function select(string $directory, ?string $base, ?string $head, ?string $config = null): array
    {
        $this->assertCheckoutRoot($directory);
        if (null === $base && null === $head) {
            return $this->withoutIgnored($directory, $this->paths($this->process->capture(['git', 'ls-files', '-z'], $directory)));
        }

        foreach ([$base, $head] as $sha) {
            if (null === $sha || 1 !== preg_match('/\A[a-fA-F0-9]{40}\z/D', $sha)) {
                throw new \InvalidArgumentException('Changed mode requires immutable 40-character base and head SHAs.');
            }
            $this->process->capture(['git', 'cat-file', '-e', $sha . '^{commit}'], $directory);
        }

        if (trim($this->process->capture(['git', 'rev-parse', 'HEAD'], $directory)) !== strtolower($head)) {
            throw new \RuntimeException('Checkout HEAD differs from the requested PR head.');
        }
        if ('' !== $this->process->capture(['git', 'status', '--porcelain', '--untracked-files=no'], $directory)) {
            throw new \RuntimeException('Changed mode requires a clean tracked checkout of the requested head.');
        }

        // No shell, newline parsing or rename-source parsing. Deleted paths are never passed to tools.
        $allChanges = $this->paths($this->process->capture([
            'git', 'diff', '--name-only', '-z', $base . '...' . $head, '--',
        ], $directory));
        foreach ($allChanges as $path) {
            if ((null !== $config && $directory . '/' . $path === $config)
                || 1 === preg_match('~(^|/)(composer\.(json|lock)|ecs[^/]*\.php|\.editorconfig|\.gitignore)$|^src/Config/|^config/~', $path)) {
                return $this->withoutIgnored($directory, $this->paths($this->process->capture(['git', 'ls-files', '-z'], $directory)));
            }
        }

        return $this->withoutIgnored($directory, $this->paths($this->process->capture([
            'git', 'diff', '--name-only', '--diff-filter=ACMRT', '--find-renames', '-z', $base . '...' . $head, '--',
        ], $directory)));
    }

    /**
     * Applies only the consumer's per-directory .gitignore rules, including to forced tracked paths.
     *
     * Git owns anchored patterns, negations, nested rules and binary-safe paths. Global/info excludes
     * are deliberately absent so host-specific preferences cannot silently change the project check.
     *
     * @param list<string> $paths Candidate tracked destinations from the selected scan mode.
     * @return list<string> Destinations not ignored by the consumer's current checkout rules.
     */
    private function withoutIgnored(string $directory, array $paths): array
    {
        $ignored = $this->paths($this->process->capture([
            'git', 'ls-files', '--cached', '--ignored', '--exclude-per-directory=.gitignore', '-z',
        ], $directory));

        return array_values(array_diff($paths, $ignored));
    }

    /**
     * Validates Git's scope metadata without interpreting the absolute root pathname.
     *
     * @throws \InvalidArgumentException When the directory is nested, bare or inside Git metadata.
     */
    private function assertCheckoutRoot(string $directory): void
    {
        $inside = trim($this->process->capture(['git', 'rev-parse', '--is-inside-work-tree'], $directory));
        // A nested prefix always ends in '/'; removing line terminators cannot turn it into a root.
        $prefix = rtrim($this->process->capture(['git', 'rev-parse', '--show-prefix'], $directory), "\r\n");

        if ('true' !== $inside || '' !== $prefix) {
            throw new \InvalidArgumentException('Project directory must be the Git checkout root.');
        }
    }

    /** @return list<string> Decodes Git's binary path protocol without trimming file names. */
    private function paths(string $output): array
    {
        return array_values(array_filter(explode("\0", $output), static fn(string $path): bool => '' !== $path));
    }
}
