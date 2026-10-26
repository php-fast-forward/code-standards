<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\ServiceProvider;

use FastForward\CodeStandards\Console\Application;
use FastForward\CodeStandards\Console\Command\CheckCommand;
use FastForward\CodeStandards\Console\Command\FixCommand;
use FastForward\CodeStandards\Console\Command\RefactorCommand;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\ServiceProvider\StandardsServiceProvider;
use FastForward\Container\ContainerFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

/** Verifies actual published Container autowiring and native attributed-command binding. */
final class CompositionTest extends TestCase
{
    /** Overrides are selected before construction and both commands retain explicit write authority and status. */
    public function testAutowiringAndTransportOverrideComposeDistinctCommands(): void
    {
        foreach (['check' => false, 'fix' => true] as $name => $write) {
            $process = new RecordingTransport();
            $constructions = 0;
            $override = new ArrayServiceProvider([
                ProcessRunner::class => static function () use ($process, &$constructions): ProcessRunner {
                    ++$constructions;

                    return $process;
                },
            ]);
            $container = ContainerFactory::create(new AggregateServiceProvider(
                new StandardsServiceProvider(dirname(__DIR__, 2) . '/vendor', new BufferedOutput()),
                $override,
            ));
            self::assertTrue($container->has(Application::class));
            self::assertSame(0, $constructions);

            $application = $container->get(Application::class);
            self::assertSame($application, $container->get(Application::class));
            self::assertSame(1, $constructions);
            self::assertInstanceOf(CheckCommand::class, $container->get(CheckCommand::class));
            self::assertInstanceOf(FixCommand::class, $container->get(FixCommand::class));
            self::assertFalse(is_subclass_of(CheckCommand::class, Command::class));
            self::assertFalse(is_subclass_of(FixCommand::class, Command::class));
            self::assertInstanceOf(RefactorCommand::class, $container->get(RefactorCommand::class));
            self::assertFalse(is_subclass_of(RefactorCommand::class, Command::class));

            $tester = new ApplicationTester($application);
            self::assertSame(23, $tester->run(['command' => $name, '--all' => true, '--directory' => dirname(__DIR__, 2)]));
            self::assertSame($write, in_array('--fix', $process->commands[array_key_last($process->commands)], true));
            self::assertStringContainsString('Selected ', $tester->getDisplay());
        }
    }

    /** A later output binding is used by the real native transport without interpreting subprocess markup. */
    public function testOutputOverridePreservesRawProcessBytes(): void
    {
        $default = new BufferedOutput();
        $replacement = new BufferedOutput();
        $container = ContainerFactory::create(new AggregateServiceProvider(
            new StandardsServiceProvider(dirname(__DIR__, 2) . '/vendor', $default),
            new ArrayServiceProvider([OutputInterface::class => static fn (): OutputInterface => $replacement]),
        ));

        $status = $container->get(ProcessRunner::class)->run([PHP_BINARY, '-r', 'echo "<info>literal</info>";'], sys_get_temp_dir());
        self::assertSame(0, $status);
        self::assertSame('', $default->fetch());
        self::assertSame('<info>literal</info>', $replacement->fetch());
    }

    /** A failed explicit dependency remains a construction error with its original cause. */
    public function testFailedBindingPreservesItsCause(): void
    {
        $cause = new \RuntimeException('Synthetic transport construction failure.');
        $container = ContainerFactory::create(new AggregateServiceProvider(
            new StandardsServiceProvider(dirname(__DIR__, 2) . '/vendor', new BufferedOutput()),
            new ArrayServiceProvider([ProcessRunner::class => static fn (): ProcessRunner => throw $cause]),
        ));

        try {
            $container->get(Application::class);
            self::fail('Failed binding must not be replaced by an implicit default.');
        } catch (\Psr\Container\ContainerExceptionInterface $failure) {
            while (null !== $failure->getPrevious()) {
                $failure = $failure->getPrevious();
            }

            self::assertSame($cause, $failure);
        }
    }

    /** Invalid installation paths fail before building any service graph. */
    public function testInvalidVendorDirectoryHasAnObjectiveError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('readable installed ECS entrypoint');
        new StandardsServiceProvider('', new BufferedOutput());
    }
}

/** Captures tool authority/status while using real read-only Git selection in this checkout. */
final class RecordingTransport implements ProcessRunner
{
    /** @var list<list<string>> Tool arguments observed without executing syntax/style processes. */
    public array $commands = [];

    /** Reads only Git metadata through the production shell-free transport. */
    public function capture(array $arguments, string $directory): string
    {
        return (new NativeProcessRunner())->capture($arguments, $directory);
    }

    /** Returns a synthetic style failure to verify end-to-end exit-code propagation. */
    public function run(array $arguments, string $directory): int
    {
        $this->commands[] = $arguments;

        return in_array('-l', $arguments, true) ? 0 : 23;
    }
}
