<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Console\Input\MagicNumbersInput;
use FastForward\CodeStandards\Console\MagicNumbersOperation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Exposes optional magic-number analysis independently of formatting and dependency checks. */
#[AsCommand(
    name: 'magic-numbers',
    description: 'Analyze selected PHP files with PHPMND without changing source.',
)]
final readonly class MagicNumbersCommand
{
    /** Injects the explicit analysis operation through native Container autowiring. */
    public function __construct(private MagicNumbersOperation $operation) {}

    /** Preserves analysis status; help and argument binding remain owned by Symfony. */
    public function __invoke(
        SymfonyStyle $io,

        #[MapInput]
        MagicNumbersInput $settings,
    ): int {
        return $this->operation->run($settings, $io);
    }
}
