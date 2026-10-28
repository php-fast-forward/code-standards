<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console\Command;

use FastForward\CodeStandards\Output\OutputFormat;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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

        #[Option(description: 'Required existing native Rector PHP configuration with an explicit target PHP; cache configuration is optional.')]
        ?string $config = null,

        #[Option(description: 'Project directory; relative configuration paths resolve here.', shortcut: 'C')]
        ?string $directory = null,

        #[Option(description: 'Explicitly apply the selected semantic changes, then verify with a dry-run.')]
        bool $apply = false,

        #[Option(description: 'Positive whole seconds per Rector subprocess, including preflight and verification.')]
        string $timeout = '300',

        #[Option(description: 'Native output; JSON is preview only and cannot be combined with --apply.', suggestedValues: [self::class, 'suggestFormats'])]
        string $format = OutputFormat::Text->value,
    ): int {
        try {
            if (null === $config) {
                throw new InvalidArgumentException('Select an explicit native Rector configuration with --config.');
            }

            $seconds = $this->timeoutSeconds($timeout);
            $scope = $this->projects->resolve(true, null, null, $directory, $config);

            return $this->rector->run(directory: $scope->directory, config: $scope->config, apply: $apply, timeout: $seconds, format: $format);
        } catch (ProcessFailedException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return $error->getProcess()->getExitCode() ?? Command::FAILURE;
        } catch (ProcessTimedOutException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return Command::FAILURE;
        } catch (InvalidArgumentException|RuntimeException $error) {
            $io->getErrorStyle()->error($error->getMessage());

            return Command::INVALID;
        }
    }

    /** @return list<string> Enum-derived suggestions filtered by the same native format policy as execution. */
    public static function suggestFormats(CompletionInput $input): array
    {
        return array_values(array_filter(OutputFormat::values(), static fn(string $format): bool => RectorRunner::supportsFormat(OutputFormat::from($format))));
    }

    /** Preserves the literal option contract instead of silently coercing decimal, exponent or overflowing input. */
    private function timeoutSeconds(string $timeout): int
    {
        $seconds = (int) $timeout;

        if (
            1 !== preg_match('/^[1-9][0-9]*$/D', $timeout)
            || (string) $seconds !== $timeout
        ) {
            throw new InvalidArgumentException('--timeout must be a positive whole number of seconds.');
        }

        return $seconds;
    }
}
