<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

use Symfony\Component\Process\Process;

/** Native transport; callers own selection and operation policy. */
final class NativeProcessRunner implements ProcessRunner
{
    /** Captures Git output as bytes, including NUL-delimited paths. */
    public function capture(array $arguments, string $directory): string
    {
        $process = new Process($arguments, $directory, timeout: 120);
        $process->mustRun();

        return $process->getOutput();
    }

    /** Runs one tool, streaming its output without changing its failure status. */
    public function run(array $arguments, string $directory): int
    {
        $process = new Process($arguments, $directory, [
            'GH_TOKEN' => false,
            'GITHUB_TOKEN' => false,
            'COMPOSER_AUTH' => false,
        ], timeout: 300);

        return $process->run(static function (string $type, string $output): void {
            fwrite(Process::ERR === $type ? STDERR : STDOUT, $output);
        });
    }
}
