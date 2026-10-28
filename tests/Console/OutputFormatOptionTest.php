<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Console;

use FastForward\CodeStandards\Console\Application;
use FastForward\CodeStandards\Output\OutputFormat;
use FastForward\CodeStandards\Process\ProcessRunner;
use FastForward\CodeStandards\ServiceProvider\StandardsServiceProvider;
use FastForward\Container\ContainerFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandCompletionTester;

/** Exercises native option completion independently of explicit format validation and process execution. */
final class OutputFormatOptionTest extends TestCase
{
    /** Attribute constants retain enum values, native default and each command's supported subset. */
    public function testCompletionSuggestsTheSupportedEnumValuesWithoutRunningTools(): void
    {
        $application = $this->application();
        foreach (['--format', '--format='] as $option) {
            $input = '--format' === $option ? [$option, ''] : [$option];
            self::assertSame(
                array_column(OutputFormat::cases(), 'value'),
                (new CommandCompletionTester(command: $application->find('check')))->complete(input: $input),
            );
            self::assertSame(
                [OutputFormat::Text->value, OutputFormat::Json->value],
                (new CommandCompletionTester(command: $application->find('refactor')))->complete(input: $input),
            );
        }
        self::assertSame(OutputFormat::Text->value, $application->find('check')->getDefinition()->getOption('format')->getDefault());
        self::assertSame(OutputFormat::Text->value, $application->find('refactor')->getDefinition()->getOption('format')->getDefault());
    }

    /** Suggestions grant no execution authority and do not change the existing invalid-format diagnostic/exit. */
    public function testCheckRejectsInvalidValuesBeforeSelectionAndKeepsItsErrorContract(): void
    {
        $tester = new ApplicationTester(application: $this->application());
        foreach (['xml', 'JSON', ''] as $format) {
            self::assertSame(2, $tester->run(input: ['command' => 'check', '--format' => $format, '--directory' => '/unavailable-project'], options: ['capture_stderr_separately' => true]));
            self::assertStringContainsString('Check format must be text, json or json-pretty.', $tester->getErrorOutput());
            self::assertSame('', $tester->getDisplay());
        }
    }

    /** Rector rejects the check-only indented representation before native preflight, retaining its own error. */
    public function testRefactorRejectsUnsupportedEnumValuesBeforeNativeWork(): void
    {
        $config = tempnam(sys_get_temp_dir(), 'format-option-');
        try {
            $tester = new ApplicationTester(application: $this->application());
            self::assertSame(2, $tester->run(input: ['command' => 'refactor', '--config' => $config, '--format' => OutputFormat::JsonPretty->value], options: ['capture_stderr_separately' => true]));
            self::assertStringContainsString('Rector format must be text or json.', $tester->getErrorOutput());
            self::assertSame('', $tester->getDisplay());
        } finally {
            unlink($config);
        }
    }

    /** Builds the actual attributed commands with a transport that cannot execute during completion or rejection. */
    private function application(): Application
    {
        $process = $this->createMock(ProcessRunner::class);
        $process->expects(self::never())->method('capture');
        $process->expects(self::never())->method('run');
        $container = ContainerFactory::create(new AggregateServiceProvider(
            new StandardsServiceProvider(vendorDirectory: dirname(__DIR__, 2) . '/vendor', output: new BufferedOutput()),
            new ArrayServiceProvider([ProcessRunner::class => static fn(): ProcessRunner => $process]),
        ));

        return $container->get(Application::class);
    }
}
