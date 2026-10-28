<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Refactoring;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Refactoring\RectorJsonReportParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Qualifies reusable validation without a workflow runtime, analyzer or executable configuration. */
final class RectorJsonReportParserTest extends TestCase
{
    /** Native optional fields, opaque diffs and record order survive successful validation. */
    public function testNativeReportIsRetainedWithoutNormalization(): void
    {
        $path = "space and\nnewline.php";
        $report = [
            'file_diffs' => [['file' => $path, 'diff' => "@@ -1 +2 @@\n-<old>\n+<new>\n", 'applied_rectors' => ['NativeRule']]],
            'errors' => [['message' => 'Native processing failure', 'file' => null, 'line' => null]],
            'changed_files' => [$path],
            'totals' => ['changed_files' => 1, 'errors' => 1],
            'native_extension' => ['retained' => true],
        ];
        $process = new ProcessResult(1, json_encode($report, JSON_THROW_ON_ERROR), 'native stderr');

        self::assertSame($report, (new RectorJsonReportParser())->parse($process));
        self::assertSame('native stderr', $process->stderr);
    }

    /** Native clean reports may omit empty optional lists; validation does not invent fields. */
    public function testCleanReportRetainsAbsentOptionalLists(): void
    {
        $report = ['totals' => ['changed_files' => 0, 'errors' => 0]];
        self::assertSame($report, (new RectorJsonReportParser())->parse(new ProcessResult(0, json_encode($report, JSON_THROW_ON_ERROR), '')));
    }

    /** @return iterable<string,array{string,int,string}> Existing native validation errors remain objective. */
    public static function invalidReports(): iterable
    {
        yield 'malformed JSON' => ['{"totals":', 1, 'Rector did not return a complete native JSON document.'];
        yield 'human preflight failure' => ['Error: missing explicit PHP target', 2, 'Rector did not return a complete native JSON document.'];
        yield 'missing totals' => ['{}', 0, 'Rector JSON totals are unavailable.'];
        yield 'count coercion' => ['{"totals":{"changed_files":"0","errors":0}}', 0, 'Rector JSON count is invalid.'];
        yield 'error total mismatch' => ['{"totals":{"changed_files":0,"errors":1}}', 1, 'Rector error total is inconsistent.'];
        yield 'changed paths mismatch' => ['{"totals":{"changed_files":1,"errors":0}}', 2, 'Rector changed-file list is inconsistent.'];
        yield 'invalid error location' => ['{"totals":{"changed_files":0,"errors":1},"errors":[{"message":"failure","line":0}]}', 1, 'Rector JSON error location is invalid.'];
        yield 'invalid diff location' => ['{"totals":{"changed_files":0,"errors":0},"file_diffs":[{"file":"other.php","diff":"opaque"}]}', 0, 'Rector JSON change location is invalid.'];
        yield 'keyed list' => ['{"totals":{"changed_files":0,"errors":0},"errors":{"unexpected":{}}}', 0, 'Rector JSON entries must be a list.'];
        yield 'error exit mismatch' => ['{"totals":{"changed_files":0,"errors":1},"errors":[{"message":"failure"}]}', 2, 'Rector error report contradicts its exit.'];
        yield 'clean exit mismatch' => ['{"totals":{"changed_files":0,"errors":0}}', 1, 'Rector preview exit contradicts its native totals.'];
    }

    /** Rejected reports keep the established exception class and diagnostic text. */
    #[DataProvider('invalidReports')]
    public function testInvalidReportsRetainExistingErrors(string $json, int $exit, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new RectorJsonReportParser())->parse(new ProcessResult($exit, $json, ''));
    }
}
