<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;

/** Explicit semantic refactoring is independent of the check/fix formatting operations. */
#[AsCommand(
    name: 'refactor',
    description: 'Preview an explicit native Rector configuration; apply only with --apply.',
)]
final readonly class RefactorCommand
{
    /** Reuses project/config path validation and the injected subprocess boundary. */
    public function __construct(private ProjectScopeResolver $projects, private RectorRunner $rector) {}

    /** Configured native rules own target PHP and source scope; no migration set is inferred from the host. */
    public function __invoke(
        SymfonyStyle $io,

        #[Option(description: 'Required existing native Rector PHP configuration, including explicit target PHP and cache.')]
        ?string $config = null,

        #[Option(description: 'Project directory; relative configuration paths resolve here.', shortcut: 'C')]
        ?string $directory = null,

        #[Option(description: 'Explicitly apply the selected semantic changes, then verify with a dry-run.')]
        bool $apply = false,
    ): int {
        try {
            if (null === $config) {
                throw new InvalidArgumentException('Select an explicit native Rector configuration with --config.');
            }
            $scope = $this->projects->resolve(true, null, null, $directory, $config);

            return $this->rector->run($scope->directory, $scope->config, $apply);
        } catch (ProcessFailedException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return $error->getProcess()->getExitCode() ?? Command::FAILURE;
        } catch (InvalidArgumentException|RuntimeException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return Command::INVALID;
        }
    }
}
