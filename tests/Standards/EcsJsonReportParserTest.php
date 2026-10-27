<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Standards;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Standards\EcsJsonReportParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Qualifies native report structure rather than inferring findings from exit status alone. */
final class EcsJsonReportParserTest extends TestCase
{
    /** Native empty-array files and a clean status form a complete clean report. */
    public function testNativeCleanReportIsAccepted(): void
    {
        $result = new ProcessResult(0, '{"totals":{"errors":0,"diffs":0},"files":[]}', 'warning');
        self::assertSame(['errors' => 0, 'diffs' => 0, 'files' => []], (new EcsJsonReportParser())->parse($result, ['/source.php']));
        $reordered = new ProcessResult(0, '{"files":[],"totals":{"diffs":0,"errors":0}}', '');
        self::assertSame(['errors' => 0, 'diffs' => 0, 'files' => []], (new EcsJsonReportParser())->parse($reordered, ['/source.php']));
    }

    /** Valid diagnostics and diffs retain exact paths, messages and checker names. */
    public function testNativeFindingRecordsAreRetained(): void
    {
        $path = "/space and\nnewline.php";
        $file = [
            'errors' => [['line' => 3, 'file_path' => $path, 'message' => 'Example', 'source_class' => 'ExampleSniff']],
            'diffs' => [['diff' => "--- original\n+++ new\n", 'applied_checkers' => ['ExampleFixer']]],
        ];
        $result = new ProcessResult(2, json_encode(['totals' => ['errors' => 1, 'diffs' => 1], 'files' => [$path => $file]], JSON_THROW_ON_ERROR), '');
        $report = (new EcsJsonReportParser())->parse($result, [$path]);
        self::assertSame($file, $report['files'][$path]);
        self::assertSame(1, $report['errors']);
        self::assertSame(1, $report['diffs']);
    }

    /** Omitted system errors, unexpected status and contaminated/truncated reports fail closed. */
    public function testUnprovenOrMalformedReportsAreRejected(): void
    {
        $cases = [
            [2, '{"totals":{"errors":1,"diffs":0},"files":[]}'],
            [2, '{"totals":{"errors":0,"diffs":0},"files":[]}'],
            [1, '{"totals":{"errors":0,"diffs":0},"files":[]}'],
            [0, 'banner {"totals":{"errors":0,"diffs":0},"files":[]}'],
            [0, '{"totals":{"errors":0,"diffs":0},"files":[]} trailing'],
            [0, '{"totals":'],
            [0, '{"totals":{"errors":"0","diffs":0},"files":[]}'],
            [0, '{"totals":{"errors":0,"diffs":0},"files":[],"extra":true}'],
            [2, '{"totals":{"errors":0,"diffs":1},"files":{"/other.php":{"diffs":[{"diff":"x","applied_checkers":["X"]}]}}}'],
            [2, '{"totals":{"errors":0,"diffs":1},"files":{"/source.php":{"diffs":[{"diff":"x","applied_checkers":[]}]}}}'],
        ];
        foreach ($cases as [$status, $json]) {
            try {
                (new EcsJsonReportParser())->parse(new ProcessResult($status, $json, ''), ['/source.php']);
                self::fail('Unproven report accepted: ' . $json);
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('ECS', $exception->getMessage());
            }
        }
    }
}
