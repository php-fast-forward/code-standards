<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Process;

/** Retains one completed subprocess status and its separate, unmodified output channels. */
final readonly class ProcessResult
{
    /**
     * @param int $exitCode Native completed-process status; nonzero values remain ordinary results.
     * @param string $stdout Exact stdout bytes, including control characters or invalid UTF-8.
     * @param string $stderr Exact stderr bytes; no concatenation or diagnostic parsing occurs here.
     */
    public function __construct(public int $exitCode, public string $stdout, public string $stderr) {}
}
