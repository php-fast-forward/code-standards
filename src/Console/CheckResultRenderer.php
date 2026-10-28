<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Standards\CheckResult;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Renders the versioned check contract independently of selection and native execution. */
final readonly class CheckResultRenderer
{
    /** Writes one uncolored JSON document, preserving diagnostics and rejecting unrepresentable bytes. */
    public function render(CheckResult $result, SymfonyStyle $io, bool $pretty = false): int
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0);
        try {
            $json = json_encode($result->toArray(), $flags);
        } catch (JsonException) {
            $result = new CheckResult('operational-error', 1, false, $result->selected, $result->syntaxChecked, $result->styleSubmitted, errors: [
                ['phase' => 'encoding', 'message' => 'Check output contains bytes that cannot be represented as JSON.'],
            ]);
            $json = json_encode($result->toArray(), $flags);
        }
        if ([] !== $result->errors) {
            $io->getErrorStyle()->writeln('Code Standards check did not complete successfully.', OutputInterface::OUTPUT_RAW);
        }
        if ('' !== $result->stderr) {
            $io->getErrorStyle()->write($result->stderr, false, OutputInterface::OUTPUT_RAW);
        }
        $io->writeln($json, OutputInterface::OUTPUT_RAW);

        return $result->exitCode;
    }
}
