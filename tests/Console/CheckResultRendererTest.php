<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Console;

use FastForward\CodeStandards\Console\CheckResultRenderer;
use FastForward\CodeStandards\Standards\CheckResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Qualifies representation changes without running a consumer configuration or subprocess. */
final class CheckResultRendererTest extends TestCase
{
    /** Both representations retain exact diagnostic data even when console decoration is enabled. */
    public function testRepresentationsPreserveTheContractWithoutAnsiOrConsoleMarkup(): void
    {
        $result = new CheckResult(
            status: 'syntax-failed',
            exitCode: 255,
            complete: false,
            selected: 2,
            syntaxChecked: 1,
            styleSubmitted: 0,
            errors: [['phase' => 'syntax', 'file' => "/space and\nnewline.php", 'stdout' => "\033[31m<error>native</error>\033[0m"]],
            stderr: 'native stderr',
        );

        foreach ([false, true] as $pretty) {
            [$status, $output] = $this->render(result: $result, pretty: $pretty);
            self::assertSame(255, $status);
            self::assertStringNotContainsString("\033", $output);
            self::assertSame(json_encode(value: $result->toArray(), flags: JSON_THROW_ON_ERROR), json_encode(value: json_decode(json: $output, flags: JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR));
            self::assertSame($pretty, str_contains($output, "\n    \"schema_version\""));
            if (!$pretty) {
                self::assertSame(1, substr_count($output, "\n"));
            }
        }
    }

    /** Invalid UTF-8 produces the same honest encoding failure in compact and indented form. */
    public function testEncodingFailureRetainsCoverageAndUsesTheSelectedRepresentation(): void
    {
        $result = new CheckResult(status: 'findings', exitCode: 2, complete: true, selected: 2, syntaxChecked: 2, styleSubmitted: 2, stderr: "invalid \xff");
        foreach ([false, true] as $pretty) {
            [$status, $output] = $this->render(result: $result, pretty: $pretty);
            $report = json_decode(json: $output, associative: true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $status);
            self::assertSame('operational-error', $report['status']);
            self::assertFalse($report['complete']);
            self::assertSame(2, $report['selection']['style_submitted']);
            self::assertSame('encoding', $report['errors'][0]['phase']);
            self::assertSame('', $report['diagnostics']['stderr']);
            self::assertSame($pretty, str_contains($output, "\n    \"schema_version\""));
        }
    }

    /** @return array{int,string} Rendered status and stdout with decoration forced on. */
    private function render(CheckResult $result, bool $pretty): array
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput(decorated: true);
        $io = $this->getMockBuilder(SymfonyStyle::class)
            ->setConstructorArgs([$input, $output])
            ->onlyMethods(['getErrorStyle'])
            ->getMock();
        $io->expects(self::atLeastOnce())->method('getErrorStyle')->willReturn(new SymfonyStyle(input: $input, output: new BufferedOutput()));
        $status = (new CheckResultRenderer())->render(result: $result, io: $io, pretty: $pretty);

        return [$status, $output->fetch()];
    }
}
