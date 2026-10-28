<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Refactoring\RectorJsonReportParser;
use FastForward\CodeStandards\Workflow\NativeReportParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

foreach (['AnalysisResult', 'BoundedProcess', 'NativeReportParser'] as $helper) {
    require_once dirname(__DIR__, 2) . '/.github/OptionalAnalysis/' . $helper . '.php';
}

/** Qualifies the installed native report schema and exits rather than scraping human diagnostics. */
final class RectorJsonIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/rector-json fixture-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $configuration = <<<'PHP'
            <?php
            use Rector\Config\RectorConfig;
            use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
            use Rector\ValueObject\PhpVersion;

            return RectorConfig::configure()
                ->withPhpVersion(PhpVersion::PHP_74)
                ->withPaths([__DIR__ . '/source.php'])
                ->withRules([LongArrayToShortArrayRector::class])
                ->withCache(__DIR__ . '/cache')
                ->withoutParallel();
            PHP;
        file_put_contents($this->directory . '/rector.php', $configuration);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Findings and clean preview have distinguishable native totals/status and never rewrite source. */
    public function testNativeJsonDistinguishesFindingsAndClean(): void
    {
        foreach ([['<?php $items = array(1);', 2, 1], ['<?php $items = [1];', 0, 0]] as [$source, $status, $changes]) {
            file_put_contents($this->directory . '/source.php', $source);
            $process = $this->preview();
            self::assertSame($status, $process->getExitCode(), $process->getErrorOutput());
            $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($report, (new RectorJsonReportParser())->parse(new ProcessResult(exitCode: $status, stdout: $process->getOutput(), stderr: $process->getErrorOutput())));
            self::assertSame($changes, $report['totals']['changed_files']);
            self::assertSame(0, $report['totals']['errors']);
            $classified = (new NativeReportParser(rectorReports: new RectorJsonReportParser()))->rector(new ProcessResult(exitCode: $status, stdout: $process->getOutput(), stderr: $process->getErrorOutput()));
            self::assertSame(0 === $changes ? 'clean' : 'findings', $classified->status);
            self::assertSame($changes, $classified->findingCount);
            self::assertSame($source, file_get_contents($this->directory . '/source.php'));
            if (0 !== $changes) {
                self::assertCount(1, $report['file_diffs']);
                self::assertStringContainsString('@@', $report['file_diffs'][0]['diff']);
            }
        }
    }

    /** A real parse failure is native error data with a failing exit, not a clean or advisory finding. */
    public function testNativeJsonRetainsOperationalErrors(): void
    {
        $source = '<?php function broken(';
        file_put_contents($this->directory . '/source.php', $source);
        $process = $this->preview();
        self::assertSame(1, $process->getExitCode(), $process->getErrorOutput());
        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($report, (new RectorJsonReportParser())->parse(new ProcessResult(exitCode: 1, stdout: $process->getOutput(), stderr: $process->getErrorOutput())));
        self::assertGreaterThan(0, $report['totals']['errors']);
        self::assertNotEmpty($report['errors']);
        self::assertArrayHasKey('file', $report['errors'][0]);
        $classified = (new NativeReportParser(rectorReports: new RectorJsonReportParser()))->rector(new ProcessResult(exitCode: 1, stdout: $process->getOutput(), stderr: $process->getErrorOutput()));
        self::assertSame('operational-error', $classified->status);
        self::assertTrue($classified->blocks('report'));
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
    }

    /** One CLI invocation retains the existing native preflight and preview path with explicit output selection. */
    private function preview(): Process
    {
        $process = new Process([
            PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'refactor',
            '--directory=' . $this->directory, '--config=rector.php', '--format=json',
        ], $this->directory, ['CI' => 'true', 'GITHUB_ACTIONS' => 'true'], timeout: 120);
        $process->run();

        return $process;
    }
}
