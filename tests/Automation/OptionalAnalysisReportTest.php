<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Workflow\AnalysisResult;
use FastForward\CodeStandards\Workflow\NativeReportParser;
use FastForward\CodeStandards\Workflow\SummaryRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

foreach (['AnalysisResult', 'BoundedProcess', 'NativeReportParser', 'SummaryRenderer'] as $helper) {
    require_once dirname(__DIR__, 2) . '/.github/OptionalAnalysis/' . $helper . '.php';
}

/** Verifies classification, native count meaning and rendering of untrusted data without starting analyzers. */
final class OptionalAnalysisReportTest extends TestCase
{
    /** A clean report's fileCount is not the number of selected files. */
    public function testMagicNumbersUsesTheSelectionForCoverageAndNativeEntriesForFindings(): void
    {
        if (!extension_loaded('dom')) {
            self::markTestSkipped('Native XML classification requires the real DOM extension.');
        }
        $parser = new NativeReportParser();
        $clean = $parser->magicNumbers(new ProcessResult(0, $this->xml(), ''), ['/consumer/a.php', '/consumer/b.php']);
        self::assertSame('clean', $clean->status);
        self::assertSame(2, $clean->selectedCount);
        self::assertSame(0, $clean->findingCount);

        $found = $parser->magicNumbers(new ProcessResult(1, $this->xml('<file path="/consumer/a.php" errors="1"><entry line="7" start="0" end="2"><snippet><![CDATA[<?php if ($x === 42) echo "<!DOCTYPE html>";]]></snippet><suggestions/></entry></file>', 1, 1), ''), ['/consumer/a.php']);
        self::assertSame('findings', $found->status);
        self::assertSame(1, $found->findingCount);
        self::assertSame(7, $found->diagnostics[0]['line']);
        self::assertFalse($found->blocks('report'));
        self::assertTrue($found->blocks('fail'));
    }

    /** @return iterable<string, array{string, int}> Invalid/partial output must never be accepted as advisory debt. */
    public static function invalidMagicReports(): iterable
    {
        yield 'not XML' => ['not XML', 1];
        yield 'truncated XML' => ['<phpmnd version="3.6.1" fileCount="0" errorCount="0"><files>', 1];
        yield 'wrong exit' => ['<phpmnd version="3.6.1" fileCount="0" errorCount="0"><files/></phpmnd>', 1];
        yield 'overflow' => ['<phpmnd version="3.6.1" fileCount="0" errorCount="999999999999999999999999"><files/></phpmnd>', 0];
        yield 'counts' => ['<phpmnd version="3.6.1" fileCount="0" errorCount="1"><files/></phpmnd>', 1];
        yield 'unselected path' => ['<phpmnd version="3.6.1" fileCount="1" errorCount="1"><files><file path="/outside/a.php" errors="1"><entry line="1"/></file></files></phpmnd>', 1];
        yield 'entity declaration' => ['<!DOCTYPE phpmnd [<!ENTITY secret SYSTEM "file:///not-read">]><phpmnd version="3.6.1" fileCount="0" errorCount="0"><files/></phpmnd>', 0];
    }

    /** XML schema/status failures reject the report rather than returning a clean zero. */
    #[DataProvider('invalidMagicReports')]
    public function testInvalidMagicReportIsOperationalFailure(string $report, int $status): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NativeReportParser())->magicNumbers(new ProcessResult($status, $report, ''), ['/consumer/a.php']);
    }

    /** Rejecting a DTD must not first open its external subset or entity through a PHP stream wrapper. */
    public function testXmlRejectsExternalEntitiesWithoutOpeningTheirSources(): void
    {
        if (!extension_loaded('dom')) {
            self::markTestSkipped('External-entity qualification requires the real DOM extension.');
        }
        self::assertTrue(stream_wrapper_register('standards-report-probe', ExternalEntityProbe::class));
        ExternalEntityProbe::$opens = 0;
        try {
            foreach ([
                '<!DOCTYPE phpmnd SYSTEM "standards-report-probe://external-subset">',
                '<!DOCTYPE phpmnd [<!ENTITY source SYSTEM "standards-report-probe://external-entity">]>',
            ] as $declaration) {
                $report = $declaration . '<phpmnd version="3.6.1" fileCount="0" errorCount="0"><files>&source;</files></phpmnd>';
                try {
                    (new NativeReportParser())->magicNumbers(new ProcessResult(0, $report, ''), []);
                    self::fail('DTD-bearing reports must be rejected.');
                } catch (InvalidArgumentException) {
                    self::assertSame(0, ExternalEntityProbe::$opens);
                }
            }
        } finally {
            self::assertTrue(stream_wrapper_unregister('standards-report-probe'));
        }
    }

    /** Native errors dominate report-only policy even when changes were also proposed. */
    public function testRectorNativeChangesAndErrorsHaveSeparateMeaning(): void
    {
        $parser = new NativeReportParser();
        $clean = $parser->rector(new ProcessResult(0, '{"totals":{"changed_files":0,"errors":0}}', ''));
        self::assertSame('clean', $clean->status);
        self::assertNull($clean->selectedCount);

        $json = '{"totals":{"changed_files":1,"errors":0},"changed_files":["src/a.php"],"file_diffs":[{"file":"src/a.php","diff":"@@ -1 +9 @@\\n-old\\n+new"}]}';
        $changes = $parser->rector(new ProcessResult(2, $json, ''));
        self::assertSame('findings', $changes->status);
        self::assertSame(1, $changes->findingCount);
        self::assertNull($changes->diagnostics[0]['line'], 'Do not infer a line from a diff or fabricate AST identity.');
        self::assertFalse($changes->blocks('report'));

        $error = $parser->rector(new ProcessResult(1, '{"totals":{"changed_files":0,"errors":1},"errors":[{"file":"src/b.php","line":12,"message":"native failure"}]}', ''));
        self::assertSame('operational-error', $error->status);
        self::assertSame(12, $error->diagnostics[0]['line']);
        self::assertTrue($error->blocks('report'));
        self::assertTrue($error->blocks('fail'));
        self::assertSame(1, $error->nativeExit);
    }

    /** @return iterable<string, array{string, int}> ANSI, extra documents and mismatched types are not scraped away. */
    public static function invalidRectorReports(): iterable
    {
        yield 'human output' => ['Error: malformed config', 2];
        yield 'multiple JSON documents' => ['{"totals":{"changed_files":0,"errors":0}}{}', 0];
        yield 'coerced count' => ['{"totals":{"changed_files":"0","errors":0}}', 0];
        yield 'findings without list' => ['{"totals":{"changed_files":1,"errors":0}}', 2];
        yield 'clean with failure exit' => ['{"totals":{"changed_files":0,"errors":0}}', 1];
        yield 'errors without entries' => ['{"totals":{"changed_files":0,"errors":1}}', 1];
    }

    /** Controlled invocation errors sharing status 2 do not become Rector findings. */
    #[DataProvider('invalidRectorReports')]
    public function testInvalidRectorReportIsOperationalFailure(string $report, int $status): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NativeReportParser())->rector(new ProcessResult($status, $report, ''));
    }

    /** Plain preformatted HTML keeps Markdown/commands inert and bounds display without changing native totals. */
    public function testSummaryEscapesAndTruncatesUntrustedDiagnostics(): void
    {
        $malicious = "</pre>\n[link](https://example.invalid) ::error::<script>alert(1)</script>";
        $diagnostics = array_fill(0, 1000, ['file' => $malicious . str_repeat('x', 5000), 'line' => 7, 'message' => $malicious]);
        $summary = (new SummaryRenderer())->render([
            new AnalysisResult('Magic Numbers', 'findings', 1, 1000, $diagnostics, $malicious, 1),
            new AnalysisResult('Rector', 'disabled'),
        ]);
        self::assertLessThanOrEqual(65536, strlen($summary));
        self::assertStringNotContainsString('<script>', $summary);
        self::assertStringContainsString('&lt;script&gt;', $summary);
        self::assertStringContainsString('\\n[link]', $summary);
        self::assertStringContainsString('1000', $summary);
        self::assertStringContainsString('omitted', $summary);
        self::assertStringContainsString('[truncated]', $summary);
        self::assertStringContainsString('| Rector | disabled |', $summary, 'Every tool state remains visible even when earlier diagnostics fill the display budget.');
    }

    /** Disabled, empty selection and operational error are observably different from native clean. */
    public function testStatesAndPolicyDoNotConvertErrorsToAdvisorySuccess(): void
    {
        foreach (['disabled', 'not-executed', 'clean'] as $state) {
            self::assertFalse((new AnalysisResult('Magic Numbers', $state))->blocks('report'));
        }
        self::assertTrue((new AnalysisResult('Magic Numbers', 'operational-error'))->blocks('report'));
        $this->expectException(InvalidArgumentException::class);
        (new AnalysisResult('Magic Numbers', 'findings'))->blocks('ignore');
    }

    /** Creates only synthetic report text; no analyzer or consumer source is loaded. */
    private function xml(string $files = '', int $count = 0, int $fileCount = 0): string
    {
        return '<phpmnd version="3.6.1" fileCount="' . $fileCount . '" errorCount="' . $count . '"><files>' . $files . '</files></phpmnd>';
    }
}

/** Synthetic external-entity source records attempts without opening a file or performing network access. */
final class ExternalEntityProbe
{
    public static int $opens = 0;

    /** @var resource|null PHP supplies the stream context when dispatching a wrapper open. */
    public $context;

    /** No source is opened; a nonzero count alone proves an unsafe resolution attempt. */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        ++self::$opens;

        return false;
    }
}
