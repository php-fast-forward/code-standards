<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Refactoring;

use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Refactoring\RectorRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Checks write authority and the native empty-configuration initialization boundary. */
final class RectorRunnerTest extends TestCase
{
    /** Preview retains Rector's difference status and never drops its dry-run flag. */
    public function testPreviewPreservesNativeDifferencesWithoutWriteAuthority(): void
    {
        $process = new RectorTransport(statuses: [2]);
        self::assertSame(2, (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/my policy.php', false));
        self::assertCount(1, $process->commands);
        self::assertContains('--dry-run', $process->commands[0]);
        self::assertContains('--config=/consumer/my policy.php', $process->commands[0]);
        self::assertContains('list-rules', $process->captures[0]);
        self::assertNotContains('--no-interaction', $process->commands[0]);
        self::assertSame([300.0], $process->captureTimeouts);
        self::assertSame([300.0], $process->runTimeouts);
    }

    /** Apply is a single write pass followed by a read-only verification whose failure remains observable. */
    public function testApplyVerifiesAndPropagatesResidualChanges(): void
    {
        $process = new RectorTransport(statuses: [0, 2]);
        self::assertSame(2, (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/policy.php', true));
        self::assertCount(2, $process->commands);
        self::assertNotContains('--dry-run', $process->commands[0]);
        self::assertContains('--dry-run', $process->commands[1]);

        $failed = new RectorTransport(statuses: [1]);
        self::assertSame(1, (new RectorRunner($failed, '/tool/rector'))->run('/consumer', '/consumer/policy.php', true));
        self::assertCount(1, $failed->commands);
    }

    /** Empty or malformed inventory must prevent process from auto-generating rector.php. */
    public function testEmptyOrMalformedRulesNeverReachProcess(): void
    {
        foreach (['{"rectors":[]}', '{}', 'not JSON', '{"rectors":[1]}'] as $inventory) {
            $process = new RectorTransport($inventory);
            try {
                (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/policy.php', false);
                self::fail('Invalid rule inventory must fail closed.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $process->commands);
            }
        }
    }

    /** Only the temporary preflight is removed; original config identity stays on every process command. */
    public function testTargetGuardEscapesOriginalPathAndCleansItsOwnFile(): void
    {
        $process = new RectorTransport();
        $config = "/consumer/quote'line\npolicy.php";
        (new RectorRunner($process, '/tool/rector'))->run('/consumer', $config, false);

        self::assertCount(1, $process->preflights);
        self::assertStringContainsString('$config->import(' . var_export($config, true) . ');', $process->guards[0]);
        self::assertFileDoesNotExist($process->preflights[0]);
        self::assertContains('--config=' . $config, $process->commands[0]);

        $failure = new RectorTransport();
        $failure->failCapture = true;
        try {
            (new RectorRunner($failure, '/tool/rector'))->run('/consumer', $config, true);
            self::fail('Native bootstrap failure must remain observable.');
        } catch (RuntimeException $exception) {
            self::assertSame('fixture capture failure', $exception->getMessage());
            self::assertFileDoesNotExist($failure->preflights[0]);
            self::assertSame([], $failure->commands);
        }
    }

    /** One genuine operation parameter bounds inventory, apply and verification without changing global defaults. */
    public function testExplicitTimeoutReachesEveryRectorSubprocess(): void
    {
        $process = new RectorTransport(statuses: [0, 0]);
        self::assertSame(0, (new RectorRunner($process, '/tool/rector'))->run('/consumer', '/consumer/policy.php', true, 600));
        self::assertSame([600.0], $process->captureTimeouts);
        self::assertSame([600.0, 600.0], $process->runTimeouts);
    }
}

/** Records only the execution boundary; no native configuration or source is evaluated here. */
final class RectorTransport implements ProcessRunner
{
    public array $commands = [];
    public array $captures = [];
    public array $preflights = [];
    public array $guards = [];
    public bool $failCapture = false;
    public array $captureTimeouts = [];
    public array $runTimeouts = [];

    /** @param list<int> $statuses Native process/verification outcomes in execution order. */
    public function __construct(private string $inventory = '{"rectors":["ReviewedRule"]}', private array $statuses = [0]) {}

    public function capture(array $arguments, string $directory, float $timeout = 120): string
    {
        $this->captures[] = $arguments;
        $this->captureTimeouts[] = $timeout;
        $preflight = substr($arguments[3], strlen('--config='));
        $this->preflights[] = $preflight;
        $this->guards[] = file_get_contents($preflight);
        if ($this->failCapture) {
            throw new RuntimeException('fixture capture failure');
        }

        return $this->inventory;
    }

    public function run(array $arguments, string $directory, float $timeout = 300): int
    {
        $this->commands[] = $arguments;
        $this->runTimeouts[] = $timeout;

        return array_shift($this->statuses);
    }
}
