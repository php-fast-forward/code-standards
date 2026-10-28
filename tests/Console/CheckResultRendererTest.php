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
        $result = new CheckResult('syntax-failed', 255, false, 2, 1, 0, errors: [
            ['phase' => 'syntax', 'file' => "/space and\nnewline.php", 'stdout' => "\033[31m<error>native</error>\033[0m"],
        ], stderr: 'native stderr');

        foreach ([false, true] as $pretty) {
            [$status, $output] = $this->render($result, $pretty);
            self::assertSame(255, $status);
            self::assertStringNotContainsString("\033", $output);
            self::assertSame(json_encode($result->toArray(), JSON_THROW_ON_ERROR), json_encode(json_decode($output, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR));
            self::assertSame($pretty, str_contains($output, "\n    \"schema_version\""));
            if (!$pretty) {
                self::assertSame(1, substr_count($output, "\n"));
            }
        }
    }

    /** Invalid UTF-8 produces the same honest encoding failure in compact and indented form. */
    public function testEncodingFailureRetainsCoverageAndUsesTheSelectedRepresentation(): void
    {
        $result = new CheckResult('findings', 2, true, 2, 2, 2, stderr: "invalid \xff");
        foreach ([false, true] as $pretty) {
            [$status, $output] = $this->render($result, $pretty);
            $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
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
        $io->expects(self::atLeastOnce())->method('getErrorStyle')->willReturn(new SymfonyStyle($input, new BufferedOutput()));
        $status = (new CheckResultRenderer())->render($result, $io, $pretty);

        return [$status, $output->fetch()];
    }
}
