<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Automation;

use FastForward\CodeStandards\Workflow\BoundedProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__, 2) . '/.github/OptionalAnalysis/BoundedProcess.php';

/** Exercises only synthetic owned processes on the hosted-Linux mechanism, never an analyzer or user process. */
final class OptionalAnalysisProcessTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        if ('Linux' !== PHP_OS_FAMILY) {
            self::markTestSkipped('The workflow executor is Linux-only; no weaker local process-group substitute is used.');
        }
        self::assertTrue(is_executable('/usr/bin/setsid'));
        self::assertTrue(function_exists('posix_getpgid') && function_exists('posix_getsid') && function_exists('posix_kill'));
        $this->directory = realpath(sys_get_temp_dir()) . '/optional process fixture-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    /** Literal arguments, exact raw channels and nonzero status survive without inherited tool credentials. */
    public function testBoundedCaptureRetainsChannelsAndStatus(): void
    {
        $previous = [];
        $fields = ['GH_TOKEN', 'GITHUB_TOKEN', 'COMPOSER_AUTH', 'GITHUB_OUTPUT', 'GITHUB_STEP_SUMMARY', 'GITHUB_ENV', 'GITHUB_PATH', 'GITHUB_STATE', 'ACTIONS_RUNTIME_TOKEN', 'ACTIONS_ID_TOKEN_REQUEST_TOKEN', 'ACTIONS_ID_TOKEN_REQUEST_URL'];
        foreach ($fields as $name) {
            $previous[$name] = getenv($name);
            putenv($name . '=synthetic-test-value');
        }
        $metadata = ['CI' => 'true', 'GITHUB_ACTIONS' => 'true', 'GITHUB_WORKSPACE' => $this->directory];
        foreach ($metadata as $name => $value) {
            $previous[$name] = getenv($name);
            putenv($name . '=' . $value);
        }
        try {
            $source = 'foreach (' . var_export($fields, true) . ' as $name) { if (getenv($name)!==false) exit(19); } '
                . 'foreach (' . var_export($metadata, true) . ' as $name => $value) { if (getenv($name)!==$value) exit(20); } '
                . 'fwrite(STDOUT,$argv[1]); fwrite(STDERR,"error\0\xff"); exit(2);';
            $argument = "quote' space\nliteral";
            $result = (new BoundedProcess())->capture([PHP_BINARY, '-r', $source, $argument], $this->directory, 5);
            self::assertSame(2, $result->exitCode);
            self::assertSame($argument, $result->stdout);
            self::assertSame("error\0\xff", $result->stderr);
        } finally {
            foreach ($previous as $name => $value) {
                putenv(false === $value ? $name : $name . '=' . $value);
            }
        }
    }

    /** A continuous producer cannot fill an unbounded intermediate buffer or leave its sleeping child alive. */
    public function testContinuousProducerAndOwnedDescendantStopAtTheCaptureLimit(): void
    {
        $pid = $this->directory . '/overflow-child.pid';
        try {
            (new BoundedProcess())->capture([PHP_BINARY, '-r', $this->descendantSource('while (true) { fwrite(STDOUT, str_repeat("x", 8192)); }'), $pid], $this->directory, 5);
            self::fail('Continuous output must fail at the capture cap.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('capture limit', $exception->getMessage());
        }
        $this->assertOwnedChildTerminated($pid);
    }

    /** A silent parent and sleeping child share the verified group and cannot evade the total timeout. */
    public function testTimeoutStopsOnlyTheOwnedProcessGroup(): void
    {
        $pid = $this->directory . '/timeout-child.pid';
        try {
            (new BoundedProcess())->capture([PHP_BINARY, '-r', $this->descendantSource('sleep(60);'), $pid], $this->directory, 1);
            self::fail('A silent invocation must have a finite total budget.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('timeout', $exception->getMessage());
        }
        $this->assertOwnedChildTerminated($pid);
    }

    /** stderr has its own small cap, independent of an otherwise valid stdout document. */
    public function testDiagnosticCaptureIsBoundedSeparately(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('capture limit');
        (new BoundedProcess())->capture([PHP_BINARY, '-r', 'fwrite(STDOUT,"{}"); while (true) { fwrite(STDERR,str_repeat("e",8192)); }'], $this->directory, 5);
    }

    /** Cleanup unlinks owned path replacements without following a link to another fixture's contents. */
    public function testOwnedTemporaryCleanupNeverFollowsSymlinks(): void
    {
        $outside = $this->directory . '/preserved';
        mkdir($outside);
        file_put_contents($outside . '/marker', 'preserve this owned fixture');
        foreach (['child', 'root'] as $placement) {
            $source = '$own=getenv("TMPDIR"); if($argv[2]==="root"){rmdir($own);symlink($argv[1],$own);}else{symlink($argv[1],$own."/link");file_put_contents($own."/temporary","owned");} echo $own;';
            $result = (new BoundedProcess())->capture([PHP_BINARY, '-r', $source, $outside, $placement], $this->directory, 5);
            self::assertSame(0, $result->exitCode);
            self::assertFileDoesNotExist($result->stdout);
            self::assertFalse(is_link($result->stdout));
            self::assertSame('preserve this owned fixture', file_get_contents($outside . '/marker'));
        }
    }

    /** The PID is written only by an exclusively owned synthetic sleeper, not discovered through a process listing. */
    private function descendantSource(string $parent): string
    {
        return '$child=proc_open([PHP_BINARY,"-r",\'file_put_contents($argv[1],(string)getmypid());sleep(60);\',$argv[1]],[0=>["file","/dev/null","r"],1=>["file","/dev/null","w"],2=>["file","/dev/null","w"]],$pipes);'
            . '$deadline=microtime(true)+2;while(!is_file($argv[1])&&microtime(true)<$deadline){usleep(1000);}if(!is_file($argv[1]))exit(20);'
            . $parent;
    }

    /** Reads only the known fixture PID's Linux state; a terminated zombie is not treated as a live orphan. */
    private function assertOwnedChildTerminated(string $file): void
    {
        self::assertFileExists($file);
        $pid = file_get_contents($file);
        self::assertMatchesRegularExpression('/\A[1-9][0-9]*\z/D', $pid);
        $deadline = microtime(true) + 2;
        do {
            $state = @file_get_contents('/proc/' . $pid . '/stat');
            if (false === $state || in_array(substr($state, strrpos($state, ') ') + 2, 1), ['Z', 'X'], true)) {
                self::addToAssertionCount(1);

                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        self::fail('The exclusively owned fixture descendant remains alive after cleanup.');
    }
}
