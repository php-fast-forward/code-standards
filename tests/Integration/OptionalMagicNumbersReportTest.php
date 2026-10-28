<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use FastForward\CodeStandards\Process\ProcessResult;
use FastForward\CodeStandards\Workflow\NativeReportParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

foreach (['AnalysisResult', 'BoundedProcess', 'NativeReportParser'] as $helper) {
    require_once dirname(__DIR__, 2) . '/.github/OptionalAnalysis/' . $helper . '.php';
}

/** Qualifies the summary classifier against installed PHPMND, not only synthetic XML fixtures. */
final class OptionalMagicNumbersReportTest extends TestCase
{
    /** A selected clean file and a finding retain distinct native status/count semantics without writes. */
    public function testClassifierAcceptsActualNativeReportsAndExits(): void
    {
        $directory = realpath(sys_get_temp_dir()) . '/optional-native-mnd-' . bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            (new Process(['git', 'init', '--quiet'], $directory, timeout: 5))->mustRun();
            $path = $directory . "/path with spaces\nand newline.php";
            foreach (['<?php if ($value === 1) {}' => 0, '<?php if ($value === 42) {}' => 1] as $source => $expected) {
                file_put_contents($path, $source);
                (new Process(['git', 'add', '--all'], $directory, timeout: 5))->mustRun();
                $process = new Process([
                    PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', 'magic-numbers',
                    '--directory=' . $directory, '--all', '--format=xml',
                ], $directory, timeout: 30);
                self::assertSame($expected, $process->run(), $process->getErrorOutput());
                $classified = (new NativeReportParser())->magicNumbers(new ProcessResult(
                    $expected, $process->getOutput(), $process->getErrorOutput(),
                ), [$path]);
                self::assertSame(0 === $expected ? 'clean' : 'findings', $classified->status);
                self::assertSame(1, $classified->selectedCount);
                self::assertSame($expected, $classified->findingCount);
                self::assertSame($source, file_get_contents($path));
                if (0 !== $expected) {
                    self::assertSame($path, $classified->diagnostics[0]['file']);
                    self::assertSame(1, $classified->diagnostics[0]['line']);
                }
            }
        } finally {
            (new Filesystem())->remove($directory);
        }
    }
}
