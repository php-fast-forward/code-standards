<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

use Symfony\Component\Process\Process;

/** Native transport; callers own selection and operation policy. */
final class NativeProcessRunner implements ProcessRunner
{
    /** Both captured and streamed configuration subprocesses receive the same credential policy. */
    private const array ENVIRONMENT = ['GH_TOKEN' => false, 'GITHUB_TOKEN' => false, 'COMPOSER_AUTH' => false];

    /** @param \Closure(string,string):void|null $write Explicit output sink; null keeps this library transport silent. */
    public function __construct(private readonly ?\Closure $write = null)
    {
    }

    /** Captures native output as bytes, including Git paths and Rector's JSON inventory. */
    public function capture(array $arguments, string $directory): string
    {
        $process = new Process($arguments, $directory, self::ENVIRONMENT, timeout: 120);
        $process->mustRun();

        return $process->getOutput();
    }

    /** Runs one tool, streaming its output without changing its failure status. */
    public function run(array $arguments, string $directory): int
    {
        $process = new Process($arguments, $directory, self::ENVIRONMENT, timeout: 300);

        return $process->run($this->write);
    }
}
