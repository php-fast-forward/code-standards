<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\ServiceProvider;

use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\Project\ProjectScopeResolver;
use FastForward\CodeStandards\Standards\StandardsRunner;
use FastForward\Container\Factory\CallableFactory;
use FastForward\Container\Factory\FactoryInterface;
use FastForward\Container\Factory\InvokableFactory;
use FastForward\Container\Factory\ServiceFactory;
use Interop\Container\ServiceProviderInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/** Supplies only interface/scalar bindings; Fast Forward Container autowires concrete application services. */
final readonly class StandardsServiceProvider implements ServiceProviderInterface
{
    private string $engineDirectory;

    /**
     * Captures external installation/output dependencies without opening a checkout or starting processes.
     *
     * @param string $vendorDirectory Absolute Composer vendor directory selected by the binary's autoload entrypoint.
     * @param OutputInterface $output Invocation output; ConsoleOutput supports a separate stderr channel.
     * @throws InvalidArgumentException When the supplied installation cannot provide a readable ECS entrypoint.
     */
    public function __construct(private string $vendorDirectory, private OutputInterface $output)
    {
        $engine = realpath($vendorDirectory . '/symplify/easy-coding-standard');
        $entrypoint = $engine . '/bin/ecs';
        if (!str_starts_with($vendorDirectory, '/') || false === $engine || !is_file($entrypoint) || !is_readable($entrypoint)
            || !is_readable($vendorDirectory . '/autoload.php')
        ) {
            throw new InvalidArgumentException('Vendor directory must contain a readable installed ECS entrypoint.');
        }

        $this->engineDirectory = $engine;
    }

    /** @return array<string, FactoryInterface> Lazy native factories; later aggregate-provider bindings may replace them. */
    public function getFactories(): array
    {
        return [
            OutputInterface::class => new ServiceFactory($this->output),
            ProcessRunner::class => new CallableFactory($this->process(...)),
            ProjectScopeResolver::class => new InvokableFactory(ProjectScopeResolver::class, dirname(__DIR__, 2) . '/config/ecs.php'),
            StandardsRunner::class => new InvokableFactory(
                StandardsRunner::class,
                ProcessRunner::class,
                dirname(__DIR__, 2) . '/bin/ecs-worker',
                $this->vendorDirectory . '/autoload.php',
                $this->engineDirectory,
            ),
        ];
    }

    /** @return array<string, callable> No default decorators; consumer extensions use the published provider contract. */
    public function getExtensions(): array
    {
        return [];
    }

    /** Binds raw subprocess bytes to the supplied output while retaining stderr when supported. */
    private function process(OutputInterface $output): ProcessRunner
    {
        return new NativeProcessRunner(static function (string $type, string $message) use ($output): void {
            $stream = Process::ERR === $type && $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stream->write($message, options: OutputInterface::OUTPUT_RAW);
        });
    }
}
