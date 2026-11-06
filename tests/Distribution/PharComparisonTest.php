<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Distribution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** A forged self-reported manifest cannot authorize executable bytes different from the reviewed build. */
final class PharComparisonTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-parity-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testMatchingExecutablePayloadsPassWithoutExecutingEither(): void
    {
        $files = ['payload.php' => '<?php echo "reviewed";', 'distribution-manifest.json' => '{"same":"manifest"}'];
        $stub = '<?php __HALT_COMPILER();';
        $this->archive('fresh', $files, $stub);
        $this->archive('committed', $files, $stub);
        self::assertSame(0, $this->compare()->getExitCode());
    }

    #[DataProvider('changes')]
    public function testChangedExecutableBytesAreRejectedBeforeExecution(string $change): void
    {
        $files = ['payload.php' => '<?php echo "reviewed";', 'distribution-manifest.json' => '{"same":"manifest"}'];
        $stub = '<?php __HALT_COMPILER();';
        $this->archive('fresh', $files, $stub);
        if ('stub' === $change) {
            $stub = '<?php file_put_contents(' . var_export($this->directory . '/executed', true) . ', "executed"); __HALT_COMPILER();';
        } elseif ('file' === $change) {
            $files['payload.php'] = '<?php echo "unreviewed";';
        } elseif ('extra' === $change) {
            $files['surprise.php'] = '<?php echo "unreviewed";';
        } else {
            unset($files['payload.php']);
        }
        $this->archive('committed', $files, $stub);
        $process = $this->compare();
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('from the fresh build', $process->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/executed');
    }

    /** @return iterable<string, array{string}> */
    public static function changes(): iterable
    {
        foreach (['stub', 'file', 'extra', 'missing'] as $change) {
            yield $change => [$change];
        }
    }

    /** @param array<string, string> $files */
    private function archive(string $name, array $files, string $stub): void
    {
        $builder = <<<'PHP'
$path = $argv[1];
$archive = new Phar($path . '.phar');
foreach (json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR) as $name => $content) {
    $archive[$name] = $content;
}
$archive->setStub($argv[3]);
$archive->setSignatureAlgorithm(Phar::SHA256);
unset($archive);
rename($path . '.phar', $path);
PHP;
        $process = new Process([
            PHP_BINARY, '-d', 'phar.readonly=0', '-r', $builder,
            $this->directory . '/' . $name, json_encode($files, JSON_THROW_ON_ERROR), $stub,
        ], timeout: 30);
        $process->mustRun();
    }

    private function compare(): Process
    {
        $process = new Process([
            PHP_BINARY, dirname(__DIR__, 2) . '/scripts/phar/compare.php',
            $this->directory . '/fresh', $this->directory . '/committed',
        ], timeout: 30);
        $process->run();

        return $process;
    }
}
