<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Console\Input\ScanInput;
use FastForward\CodeStandards\Console\StandardsOperation;
use FastForward\CodeStandards\Console\StructuredCheckOperation;
use FastForward\CodeStandards\Output\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Completion\CompletionInput;
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

        #[Option(description: 'Output representation; JSON is one nonstreaming check report.', suggestedValues: [self::class, 'suggestFormats'])]
        string $format = OutputFormat::Text->value,
    ): int {
        $outputFormat = OutputFormat::tryFrom($format);
        if (null === $outputFormat) {
            $io->getErrorStyle()->error(sprintf('Check format must be %s, %s or %s.', OutputFormat::Text->value, OutputFormat::Json->value, OutputFormat::JsonPretty->value));

            return 2;
        }
        if (OutputFormat::Text !== $outputFormat) {
            return $this->structured->run(settings: $settings, io: $io, pretty: OutputFormat::JsonPretty === $outputFormat);
        }

        return $this->operation->run(settings: $settings, io: $io, fix: false);
    }

    /** @return list<string> Native completion values; invocation still validates explicit format input. */
    public static function suggestFormats(CompletionInput $input): array
    {
        return OutputFormat::values();
    }
}
