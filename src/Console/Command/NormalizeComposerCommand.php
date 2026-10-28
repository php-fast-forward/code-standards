<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use Ergebnis\Json\Exception\NotJson;
use FastForward\CodeStandards\Composer\NormalizationRunner;
use FastForward\CodeStandards\Project\ProjectDirectoryResolver;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Composer metadata is a separate opt-in operation, independent of PHP selection and ECS configuration. */
#[AsCommand(
    name: 'normalize-composer',
    description: 'Check composer.json normalization without writing; apply metadata changes only with --apply.',
)]
final readonly class NormalizeComposerCommand
{
    /** Reuses directory validation without imposing a PHP/Git scan on project metadata. */
    public function __construct(private ProjectDirectoryResolver $directories, private NormalizationRunner $normalization) {}

    /** Preserves findings status and reports controlled input, schema and filesystem failures as INVALID. */
    public function __invoke(
        SymfonyStyle $io,

        #[Option(description: 'Project directory containing composer.json; defaults to the current directory.', shortcut: 'C')]
        ?string $directory = null,

        #[Option(description: 'Explicitly normalize composer.json only; never update composer.lock or dependencies.')]
        bool $apply = false,
    ): int {
        try {
            $root = $this->directories->resolve(true, null, null, $directory);

            return $this->normalization->run($root, $apply);
        } catch (NotJson) {
            $io->getErrorStyle()->error('Composer metadata is not valid JSON.');

            return Command::INVALID;
        } catch (InvalidArgumentException|RuntimeException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return Command::INVALID;
        }
    }
}
