<?php

declare(strict_types=1);

use FastForward\CodeStandards\Console\Application;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\ServiceProvider\StandardsServiceProvider;
use FastForward\Container\ContainerFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/** Loads production classes only; PHPUnit is deliberately absent from this subprocess. */
require $argv[1];

if (extension_loaded('dom') || class_exists('PHPUnit\\Framework\\TestCase', false)) {
    throw new RuntimeException('This fixture requires a real DOM-free runtime without loaded PHPUnit.');
}

$process = new class implements ProcessRunner {
    public int $runs = 0;

    /** Reads only the fixture's real Git metadata through the production transport. */
    public function capture(array $arguments, string $directory, float $timeout = 120): string
    {
        return (new NativeProcessRunner())->capture($arguments, $directory, $timeout);
    }

    /** Records the analyzer boundary without requiring another executable or loading application code. */
    public function run(array $arguments, string $directory, float $timeout = 300): int
    {
        ++$this->runs;

        return 17;
    }
};

$container = ContainerFactory::create(new AggregateServiceProvider(
    new StandardsServiceProvider(dirname($argv[1]), new ConsoleOutput()),
    new ArrayServiceProvider([ProcessRunner::class => static fn(): ProcessRunner => $process]),
));
$status = $container->get(Application::class)->run(new ArrayInput([
    'command' => 'magic-numbers',
    '--all' => true,
    '--directory' => $argv[2],
    '--format' => $argv[3],
]));
file_put_contents($argv[4], (string) $process->runs);
exit($status);
