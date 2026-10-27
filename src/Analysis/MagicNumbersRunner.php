<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Analysis;

use Composer\InstalledVersions;
use FastForward\CodeStandards\Process\ProcessRunner;
use InvalidArgumentException;
use Povils\PHPMND\ExtensionResolver;
use Symfony\Component\Console\Output\OutputInterface;

/** Proxies the published native analyzer using explicit files and an installation-owned working directory. */
final readonly class MagicNumbersRunner
{
    /** A single bounded invocation keeps native XML intact and rejects oversized scope before execution. */
    private const MAX_ARGUMENT_BYTES = 65536;

    /**
     * Captures transport/output and the explicitly selected installation; no consumer autoload is discovered.
     *
     * @param string $entrypoint Absolute installed PHPMND binary, supplied by the provider.
     */
    public function __construct(private ProcessRunner $process, private OutputInterface $output, private string $entrypoint) {}

    /**
     * Runs native analysis without writes, dependency checks or automatic fixes.
     *
     * @param list<string> $files Ordered absolute files validated by the shared PHP/Git selection.
     * @param string $format Text for people, or native XML without banners/ANSI/timing on stdout.
     * @param string $ignoreNumbers Native comma-separated additions to PHPMND's fixed zero/one exceptions.
     * @param string|null $ignoreFuncs Native function exceptions for argument-context analysis.
     * @param string|null $extensions Native context additions/removals; these are not file suffixes.
     * @return int Exact native exit status, or zero for empty selection; native nonzero statuses pass through unchanged.
     * @throws InvalidArgumentException When input or invocation size is outside the supported contract.
     */
    public function run(array $files, string $format, string $ignoreNumbers, ?string $ignoreFuncs, ?string $extensions): int
    {
        $arguments = $this->arguments($format, $ignoreNumbers, $ignoreFuncs, $extensions);
        if ('xml' === $format && !extension_loaded('dom')) {
            throw new InvalidArgumentException('XML format requires the PHP DOM extension. Use text format or enable DOM.');
        }
        $arguments = [...$arguments, '--', ...$files];
        if (array_sum(array_map(static fn(string $argument): int => strlen($argument) + 1, $arguments)) > self::MAX_ARGUMENT_BYTES) {
            throw new InvalidArgumentException('PHPMND invocation exceeds 64 KiB; a smaller scope or a batching increment is required.');
        }
        if ('xml' === $format) {
            $this->assertXmlInputs($files);
        }
        if ([] === $files) {
            $this->emptyOutput($format);

            return 0;
        }

        return $this->process->run($arguments, dirname($this->entrypoint, 2));
    }

    /** @return list<string> Validated native options; no opaque option forwarding can change selection or output paths. */
    private function arguments(string $format, string $ignoreNumbers, ?string $ignoreFuncs, ?string $extensions): array
    {
        if (!in_array($format, ['text', 'xml'], true)) {
            throw new InvalidArgumentException('Format must be text or xml.');
        }
        foreach ('' === $ignoreNumbers ? [] : explode(',', $ignoreNumbers) as $number) {
            if (!is_numeric($number) || !is_finite((float) $number)) {
                throw new InvalidArgumentException('Ignored numbers must be a comma-separated list of finite numeric values.');
            }
        }

        $arguments = [PHP_BINARY, $this->entrypoint, '--no-ansi', '--ignore-numbers=' . $ignoreNumbers];
        if (null !== $ignoreFuncs) {
            $arguments[] = '--ignore-funcs=' . $ignoreFuncs;
        }
        if (null !== $extensions) {
            if ('' === $extensions || in_array('', explode(',', $extensions), true)) {
                throw new InvalidArgumentException('Extensions must name native PHPMND analysis contexts.');
            }
            (new ExtensionResolver())->resolve(explode(',', $extensions));
            $arguments[] = '--extensions=' . $extensions;
        }
        if ('xml' === $format) {
            $arguments[] = '--quiet';
            $arguments[] = '--xml-output=php://stdout';
        }

        return $arguments;
    }

    /**
     * Rejects bytes the native XML reporter cannot represent before it can stream a partial document.
     *
     * XML 1.0 permits tab/newline/carriage return but excludes other C0 controls and invalid UTF-8.
     * Native DOM splits embedded CDATA terminators correctly; no source/path bytes are sanitized.
     *
     * @param list<string> $files All selected paths, not only files that eventually produce findings.
     * @throws InvalidArgumentException When a selected path or source cannot be represented in XML 1.0.
     */
    private function assertXmlInputs(array $files): void
    {
        $characters = '/\A[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*\z/u';
        foreach ($files as $file) {
            if (1 !== preg_match($characters, $file)) {
                throw new InvalidArgumentException('XML format requires UTF-8 XML 1.0 characters in every selected path.');
            }
            $source = @file_get_contents($file);
            if (false === $source) {
                throw new InvalidArgumentException('XML format could not read a selected source file.');
            }
            if (1 !== preg_match($characters, $source)) {
                throw new InvalidArgumentException('XML format requires UTF-8 XML 1.0 characters in every selected source file.');
            }
        }
    }

    /** Emits the native empty-result document even when Git selection contains no PHP files. */
    private function emptyOutput(string $format): void
    {
        if ('text' === $format) {
            $this->output->writeln('No PHP files selected.');

            return;
        }

        $version = htmlspecialchars(InstalledVersions::getPrettyVersion('povils/phpmnd') ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $this->output->write('<?xml version="1.0"?>' . "\n" . '<phpmnd version="' . $version . '" fileCount="0" errorCount="0"><files/></phpmnd>' . "\n", options: OutputInterface::OUTPUT_RAW);
    }
}
