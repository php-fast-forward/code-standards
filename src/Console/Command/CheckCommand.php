<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Console\Input\ScanInput;
use FastForward\CodeStandards\Console\StandardsOperation;
use FastForward\CodeStandards\Console\StructuredCheckOperation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Exposes read-only checking as an invocable object adapted by Symfony. */
#[AsCommand(
    name: 'check',
    description: 'Check PHP syntax and style without changing source.',
)]
final readonly class CheckCommand
{
    /** Injects the shared orchestration without parsing input or resolving a checkout. */
    public function __construct(private StandardsOperation $operation, private StructuredCheckOperation $structured) {}

    /** Returns the exact check status; this entrypoint never grants write authority. */
    public function __invoke(
        SymfonyStyle $io,

        #[MapInput]
        ScanInput $settings,

        #[Option(description: 'Output format: text, json (compact), or json-pretty (indented). JSON is one nonstreaming check report.')]
        string $format = 'text',
    ): int {
        if (in_array($format, ['json', 'json-pretty'], true)) {
            return $this->structured->run($settings, $io, 'json-pretty' === $format);
        }
        if ('text' !== $format) {
            $io->getErrorStyle()->error('Check format must be text, json or json-pretty.');

            return 2;
        }

        return $this->operation->run($settings, $io, false);
    }
}
