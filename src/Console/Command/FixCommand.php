<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Console\Input\ScanInput;
use FastForward\CodeStandards\Console\StandardsOperation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Grants explicit fix authority through a separate invocable command. */
#[AsCommand(
    name: 'fix',
    description: 'Explicitly apply ECS style fixes after syntax validation.',
)]
final readonly class FixCommand
{
    /** Injects the shared orchestration without parsing input or resolving a checkout. */
    public function __construct(private StandardsOperation $operation) {}

    /** Returns the exact fix status; callers must inspect the resulting source diff. */
    public function __invoke(
        SymfonyStyle $io,

        #[MapInput]
        ScanInput $settings,
    ): int {
        return $this->operation->run($settings, $io, true);
    }
}
