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

        #[Option(description: 'Output format: text or json. JSON is one nonstreaming check report.')]
        string $format = 'text',
    ): int {
        if ('json' === $format) {
            return $this->structured->run($settings, $io);
        }
        if ('text' !== $format) {
            $io->getErrorStyle()->error('Check format must be text or json.');

            return 2;
        }

        return $this->operation->run($settings, $io, false);
    }
}
