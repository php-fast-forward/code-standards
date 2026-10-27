<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises real provider composition and summary/output writes with disabled analyzers and synthetic files. */
final class OptionalAnalysisEntrypointTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/optional-entrypoint-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** Disabled status requires neither an existing consumer directory nor Git/native execution. */
    public function testCompositionWritesBothDisabledStatesAndACompleteControlledDecision(): void
    {
        $process = $this->process(dirname(__DIR__, 2) . '/.github/run-optional-analysis.php');
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        self::assertSame("summary-written=true\nblocking=false\n", file_get_contents($this->directory . '/outputs'));
        $summary = file_get_contents($this->directory . '/summary');
        self::assertStringContainsString('| Magic Numbers | disabled |', $summary);
        self::assertStringContainsString('| Rector | disabled |', $summary);
        self::assertStringNotContainsString('| clean |', $summary);
    }

    /** @return iterable<string, array{string}> Each runner command file must be written completely. */
    public static function partialTargets(): iterable
    {
        yield 'summary' => ['GITHUB_STEP_SUMMARY'];
        yield 'outputs' => ['GITHUB_OUTPUT'];
    }

    /** A partial write is not a successfully summarized/classified invocation. */
    #[DataProvider('partialTargets')]
    public function testPartialRunnerFileWritesFailInsteadOfClaimingCompletion(string $target): void
    {
        $source = <<<'PHP'
            <?php
            final class PartialRunnerFile {
                public mixed $context;
                private int $remaining = 10;
                public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool { return true; }
                public function stream_write(string $data): int {
                    $written = min($this->remaining, strlen($data));
                    $this->remaining -= $written;
                    return $written;
                }
                public function stream_flush(): bool { return true; }
            }
            stream_wrapper_register('standards-partial', PartialRunnerFile::class);
            PHP;
        $bootstrap = $this->directory . '/partial.php';
        file_put_contents($bootstrap, $source . "\nrequire " . var_export(dirname(__DIR__, 2) . '/.github/run-optional-analysis.php', true) . ';');
        $process = $this->process($bootstrap, [$target => 'standards-partial://runner-file']);
        self::assertNotSame(0, $process->run());
        self::assertStringContainsString('Cannot ' . ('GITHUB_OUTPUT' === $target ? 'record' : 'write'), $process->getErrorOutput());
        if ('GITHUB_STEP_SUMMARY' === $target) {
            self::assertFileDoesNotExist($this->directory . '/outputs');
        }
    }

    /** Literal fixture inputs disable both analyzers; the absent consumer cannot be used accidentally. */
    private function process(string $entrypoint, array $overrides = []): Process
    {
        return new Process([PHP_BINARY, $entrypoint], dirname(__DIR__, 2), [
            'STANDARDS_DIRECTORY' => '/nonexistent-optional-consumer',
            'STANDARDS_BASE' => str_repeat('a', 40), 'STANDARDS_HEAD' => str_repeat('b', 40),
            'STANDARDS_MAGIC_NUMBERS' => 'false', 'STANDARDS_MAGIC_NUMBERS_POLICY' => 'report',
            'STANDARDS_RECTOR' => 'false', 'STANDARDS_RECTOR_POLICY' => 'report', 'STANDARDS_RECTOR_CONFIG' => '',
            'GITHUB_STEP_SUMMARY' => $this->directory . '/summary', 'GITHUB_OUTPUT' => $this->directory . '/outputs',
            ...$overrides,
        ], timeout: 10);
    }
}
