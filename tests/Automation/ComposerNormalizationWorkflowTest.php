<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use Composer\Package\Locker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Executes the actual metadata workflow steps against disposable consumer roots. */
final class ComposerNormalizationWorkflowTest extends TestCase
{
    /** Checks normalized/non-normalized/invalid roots, quoting and lock preservation through real shell steps. */
    public function testWorkflowCommandsPropagateStatusWithoutWrites(): void
    {
        $directory = sys_get_temp_dir() . '/standards workflow café-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $consumer = $directory . '/consumer $(touch injected)';
        mkdir($consumer);
        $tool = $directory . '/reviewed tool';
        symlink(dirname(__DIR__, 2), $tool);
        $normalized = "{\n    \"name\": \"example/fixture\"\n}\n";
        $cases = [
            'normalized' => [$normalized, null, 0],
            'normalized with lock' => [$normalized, Locker::getContentHash($normalized), 0],
            'normalization needed' => ['{"description":"Fixture","name":"example/fixture"}', null, 1],
            'normalization with fresh lock' => ['{"description":"Fixture","name":"example/fixture"}', Locker::getContentHash('{"description":"Fixture","name":"example/fixture"}'), 1],
            'stale lock' => [$normalized, 'stale', 1],
            'invalid JSON' => ['{', null, 2],
            'missing manifest' => [null, null, 2],
            'missing directory' => [$normalized, null, 2],
        ];

        try {
            foreach (['repository-ci.yml', 'code-standards-check.yml'] as $file) {
                foreach ($cases as $name => [$source, $hash, $expected]) {
                    (new Filesystem())->remove([$consumer . '/composer.json', $consumer . '/composer.lock']);
                    if (null !== $source) {
                        file_put_contents($consumer . '/composer.json', $source);
                    }
                    if (null !== $hash) {
                        file_put_contents($consumer . '/composer.lock', json_encode(['content-hash' => $hash, 'packages' => []], JSON_THROW_ON_ERROR));
                    }
                    file_put_contents($consumer . '/unrelated.php', '<?php invalid PHP');
                    $before = $this->inventory($consumer);
                    $process = new Process(['bash', '-euo', 'pipefail', '-c', $this->command($file)], $directory, [
                        'STANDARDS_ROOT' => $tool,
                        'STANDARDS_DIRECTORY' => 'missing directory' === $name ? $directory . '/missing' : $consumer,
                        'XDEBUG_MODE' => 'off',
                    ], timeout: 30);
                    self::assertSame($expected, $process->run(), $file . ': ' . $name . $process->getErrorOutput());
                    self::assertSame($before, $this->inventory($consumer));
                    self::assertFileDoesNotExist($directory . '/injected');
                }
            }
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /** A single false-default input crosses both reusable layers without changing existing runner/ref inputs. */
    public function testMetadataInputIsOptInAndOwnWorkflowsEnableIt(): void
    {
        $root = dirname(__DIR__, 2) . '/.github/workflows/';
        foreach (['code-standards.yml', 'code-standards-check.yml'] as $file) {
            $workflow = file_get_contents($root . $file);
            self::assertMatchesRegularExpression('~      normalize-composer:\n        description: [^\n]+\n        default: false\n        type: boolean~', $workflow);
        }
        $public = file_get_contents($root . 'code-standards.yml');
        self::assertStringContainsString('normalize-composer: ${{ inputs.normalize-composer }}', $public);
        $internal = file_get_contents($root . 'code-standards-check.yml');
        self::assertStringContainsString("- name: Check Composer normalization\n        if: inputs.normalize-composer", $internal);
        self::assertStringContainsString('STANDARDS_ROOT: ${{ github.workspace }}/tool', $internal);
        self::assertStringContainsString('STANDARDS_DIRECTORY: ${{ github.workspace }}/consumer', $internal);
        self::assertStringContainsString('normalize-composer: true', file_get_contents($root . 'repository-pr-standards.yml'));
        self::assertStringContainsString('STANDARDS_DIRECTORY: ${{ github.workspace }}', file_get_contents($root . 'repository-ci.yml'));
        foreach (['repository-ci.yml', 'code-standards-check.yml'] as $file) {
            $command = $this->command($file);
            self::assertStringContainsString('normalize-composer "--directory=$STANDARDS_DIRECTORY"', $command);
            self::assertStringNotContainsString('--apply', $command);
            self::assertStringNotContainsString('composer update', $command);
        }
    }

    /** Extracts the exact named workflow step, so tests execute the checked-in shell command. */
    private function command(string $file): string
    {
        $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/' . $file);
        self::assertSame(1, preg_match('~      - name: Check Composer normalization\n(?:(?!      - ).*\n)*?        run: \|\n((?:          .+\n)+)~', $workflow, $matches));

        return preg_replace('/^          /m', '', $matches[1]);
    }

    /** @return array<string, string> Full file-byte inventory detects generated artifacts as well as metadata writes. */
    private function inventory(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[substr($file->getPathname(), strlen($directory) + 1)] = file_get_contents($file->getPathname());
        }
        ksort($files);

        return $files;
    }
}
