<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Exercises the owned policy through the actual CLI against exclusive synthetic consumers. */
final class MultilineConditionTest extends TestCase
{
    private string $directory;

    /** Creates a disposable tracked scope; no application autoload or dependency install occurs. */
    protected function setUp(): void
    {
        $this->directory = realpath(sys_get_temp_dir()) . '/standards-condition-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->directory . '/.build/tmp');
        file_put_contents($this->directory . '/.gitignore', "/.build/\n");
        $this->git(['init', '-q']);
    }

    /** Removes only the fixture exclusively allocated by this test. */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    /** The exact mixed-line guard reported in PR55 must fail check without writing and fix deterministically. */
    public function testDefaultCheckRejectsMixedBooleanLayout(): void
    {
        $source = file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-input.php.inc');
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);

        $check = $this->cli(['check', '--all']);

        self::assertSame(2, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        $fixed = file_get_contents($this->directory . '/source.php');
        self::assertSame(file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-expected.php.inc'), $fixed);
        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($fixed, file_get_contents($this->directory . '/source.php'));
    }

    /** @return iterable<string, array{string, bool, list<string>}> Owned source-only fixtures and deliberate scope exclusions. */
    public static function conditions(): iterable
    {
        yield 'if' => ["if (\$a || \$b\n    || \$c\n) { return; }", true, []];
        yield 'elseif' => ["if (\$a) { return; } elseif (\$a || \$b\n    || \$c\n) { return; }", true, []];
        yield 'while' => ["while (\$a || \$b\n    || \$c\n) { break; }", true, []];
        yield 'do-while' => ["do { break; } while (\$a || \$b\n    || \$c\n);", true, []];
        yield 'mixed grouping' => ["if (\$a || (\$b && \$c)\n    || \$d\n) { return; }", true, []];
        yield 'multiline call cannot mask inline parts' => ["if (check(\n    \$first,\n    \$second,\n    \$third,\n    \$fourth,\n) || \$a || \$b || \$c\n    || \$d\n) { return; }", true, []];
        yield 'call argument belongs to the condition' => ["if (check(\$a || \$b)\n    || \$outer\n) { return; }", true, []];
        yield 'array index belongs to the condition' => ["if (\$values[\$a || \$b]\n    || \$outer\n) { return; }", true, []];
        yield 'arrow expression is opaque' => ["if ((fn() => (\$a || \$b) && \$inner)() || \$c\n    || \$d\n) { return; }", true, ['fn() => ($a || $b) && $inner']];
        yield 'nested arrow expressions are opaque' => ["if ((fn() => fn() => \$a || \$b)() || \$c\n    || \$d\n) { return; }", true, ['fn() => fn() => $a || $b']];
        yield 'closure body is opaque' => ["if ((function () use (\$a, \$b) { return \$a || \$b; })() || \$c\n    || \$d\n) { return; }", true, ['return $a || $b;']];
        yield 'anonymous class body is opaque' => ["if (new class { public function value() { return \$this->a || \$this->b; } } || \$outer\n    || \$last\n) { return; }", true, ['return $this->a || $this->b;']];
        yield 'descriptions comments and strings' => ["/** Useful description and {@inheritDoc}. */\nif (check('|| && text') /* keep exactly */ || \$b // keep line note\n    || \$c\n) { return; }", true, ['/** Useful description and {@inheritDoc}. */', '/* keep exactly */', '// keep line note', "'|| && text'"]];
        yield 'single-line condition remains single-line' => ['if ($a || $b && $c) { return; }', false, ['if ($a || $b && $c)']];
        yield 'arrow-only condition contains no eligible outer operator' => ["if (\n    fn() => \$a || \$b\n) { return; }", false, ['fn() => $a || $b']];
        yield 'logical keywords are not this policy' => ["if (\$a or \$b\n    or \$c\n) { return; }", false, []];
        yield 'non-condition expression remains native policy' => ["\$result = \$a || \$b\n    || \$c;", false, []];
    }

    /**
     * Proves the new policy rather than unrelated PER differences; baseline uses an explicit native skip.
     *
     * @param list<string> $preserved Exact information or opaque body content that the new policy must retain.
     */
    #[DataProvider('conditions')]
    public function testConditionScopesPreserveTokensAndReachStableOutput(string $body, bool $mustChange, array $preserved): void
    {
        $before = $this->normalizedSource("<?php\n\ndeclare(strict_types=1);\n\n" . $body . "\n");
        $check = $this->cli(['check', '--all']);

        self::assertSame($mustChange ? 2 : 0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($before, file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        $after = file_get_contents($this->directory . '/source.php');
        self::assertSame($mustChange, $before !== $after);
        self::assertSame($this->significantTokens($before), $this->significantTokens($after));
        foreach ($preserved as $text) {
            self::assertStringContainsString($text, $after);
        }

        self::assertSame(0, $this->cli(['check', '--all'])->getExitCode());
        self::assertSame(0, $this->cli(['fix', '--all'])->getExitCode());
        self::assertSame($after, file_get_contents($this->directory . '/source.php'));
    }

    /** @return iterable<string, array{string, string}> All native ECS indentation/line-ending combinations. */
    public static function spacing(): iterable
    {
        foreach (['two spaces' => '  ', 'four spaces' => '    ', 'tabs' => "\t"] as $name => $indent) {
            yield $name . ' LF' => [$indent, "\n"];
            yield $name . ' CRLF' => [$indent, "\r\n"];
        }
    }

    /** The injected native spacing value must reflect the consumer callback, not a separate Sniff default. */
    #[DataProvider('spacing')]
    public function testNativeSpacingOverrideRemainsAuthoritative(string $indent, string $lineEnding): void
    {
        $source = file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-input.php.inc');
        $source = $this->withSpacing($source, $indent, $lineEnding);
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);
        $this->configure('$config->withSpacing(indentation: ' . var_export($indent, true) . ', lineEnding: ' . var_export($lineEnding, true) . ');');
        $arguments = ['--all', '--config=.build/ecs.php'];
        $check = $this->cli(['check', ...$arguments]);

        self::assertSame(2, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['fix', ...$arguments])->getExitCode());
        $fixed = file_get_contents($this->directory . '/source.php');
        $golden = file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-expected.php.inc');
        self::assertSame($this->withSpacing($golden, $indent, $lineEnding), $fixed);
        self::assertSame(0, $this->cli(['check', ...$arguments])->getExitCode());
        self::assertSame(0, $this->cli(['fix', ...$arguments])->getExitCode());
        self::assertSame($fixed, file_get_contents($this->directory . '/source.php'));
    }

    /** Consumer rule skips remain native, explicit and last in precedence. */
    public function testNativeSkipCanDisableTheOwnedPolicy(): void
    {
        $source = file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-input.php.inc');
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);
        $this->configure('$config->withSkip([FastForward\\CodeStandards\\Sniff\\MultilineBooleanConditionSniff::class]);');
        foreach (['check', 'fix', 'check'] as $mode) {
            $result = $this->cli([$mode, '--all', '--config=.build/ecs.php']);
            self::assertSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
            self::assertSame($source, file_get_contents($this->directory . '/source.php'));
        }
    }

    /** Existing continuation offsets remain native policy; this Sniff only separates inline operators. */
    public function testExistingContinuationOffsetIsNotSilentlyRealigned(): void
    {
        $source = file_get_contents(__DIR__ . '/../Fixtures/multiline-condition-input.php.inc');
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);
        $spacing = '$config->withSpacing(indentation: "  ");';
        $arguments = ['--all', '--config=.build/ecs.php'];
        $this->configure($spacing . '$config->withSkip([FastForward\\CodeStandards\\Sniff\\MultilineBooleanConditionSniff::class]);');
        self::assertSame(0, $this->cli(['fix', ...$arguments])->getExitCode());
        $native = file_get_contents($this->directory . '/source.php');
        self::assertStringContainsString("\n      || !is_readable(\$vendorDirectory", $native);

        $this->configure($spacing);
        self::assertSame(2, $this->cli(['check', ...$arguments])->getExitCode());
        self::assertSame($native, file_get_contents($this->directory . '/source.php'));
        self::assertSame(0, $this->cli(['fix', ...$arguments])->getExitCode());
        $fixed = file_get_contents($this->directory . '/source.php');
        self::assertStringContainsString("\n      || !is_readable(\$vendorDirectory", $fixed);
        self::assertSame($this->significantTokens($native), $this->significantTokens($fixed));
        self::assertSame(0, $this->cli(['check', ...$arguments])->getExitCode());
        self::assertSame(0, $this->cli(['fix', ...$arguments])->getExitCode());
        self::assertSame($fixed, file_get_contents($this->directory . '/source.php'));
    }

    /** Source changes in an installed owned checker invalidate unchanged consumer files without clearing other caches. */
    public function testOwnedRuleRevisionInvalidatesDefaultCache(): void
    {
        $root = dirname(__DIR__, 2);
        $copy = $this->directory . '/.build/tool';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$copy . '/src/Config', $copy . '/src/Sniff', $copy . '/vendor/symplify', $copy . '/vendor/bin']);
        copy($root . '/src/Config/ECSConfig.php', $copy . '/src/Config/ECSConfig.php');
        copy($root . '/src/Sniff/MultilineBooleanConditionSniff.php', $copy . '/src/Sniff/MultilineBooleanConditionSniff.php');
        symlink(realpath($root . '/vendor/symplify/easy-coding-standard'), $copy . '/vendor/symplify/easy-coding-standard');
        file_put_contents($copy . '/vendor/autoload.php', '<?php require ' . var_export($root . '/vendor/autoload.php', true) . '; '
            . '$loader = new Composer\\Autoload\\ClassLoader(); $loader->addPsr4("FastForward\\\\CodeStandards\\\\", ' . var_export($copy . '/src', true) . '); $loader->register(true);');
        file_put_contents($copy . '/vendor/bin/code-standards', '<?php $_composer_autoload_path = __DIR__ . "/../autoload.php"; require ' . var_export($root . '/bin/code-standards', true) . ';');
        $source = "<?php\n\ndeclare(strict_types=1);\n\nreturn 1;\n";
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);
        $command = [PHP_BINARY, $copy . '/vendor/bin/code-standards', 'check', '--all'];
        $first = $this->process($command);
        self::assertSame(0, $first->getExitCode(), $first->getOutput() . $first->getErrorOutput());

        // Replace only the disposable installation's checker, retaining its class/options and the consumer source.
        file_put_contents($copy . '/src/Sniff/MultilineBooleanConditionSniff.php', '<?php namespace FastForward\\CodeStandards\\Sniff; '
            . 'final class MultilineBooleanConditionSniff implements \\PHP_CodeSniffer\\Sniffs\\Sniff { '
            . 'public function register(): array { return [T_OPEN_TAG]; } '
            . 'public function process(\\PHP_CodeSniffer\\Files\\File $file, int $index): void { $file->addError("owned policy revision", $index, "SyntheticRevision"); } }');
        $second = $this->process($command);
        self::assertSame(2, $second->getExitCode(), $second->getOutput() . $second->getErrorOutput());
        self::assertStringContainsString('owned policy revision', $second->getOutput());
        self::assertSame($source, file_get_contents($this->directory . '/source.php'));
    }

    /** Normalizes only native upstream style first so later differences belong to the owned rule. */
    private function normalizedSource(string $source): string
    {
        file_put_contents($this->directory . '/source.php', $source);
        $this->git(['add', 'source.php']);
        $this->configure('$config->withSkip([FastForward\\CodeStandards\\Sniff\\MultilineBooleanConditionSniff::class]);');
        foreach (['fix', 'check'] as $mode) {
            $result = $this->cli([$mode, '--all', '--config=.build/ecs.php']);
            self::assertSame(0, $result->getExitCode(), $result->getOutput() . $result->getErrorOutput());
        }

        return file_get_contents($this->directory . '/source.php');
    }

    /** @return list<array{int, string}|string> Token content/kind equality excludes only whitespace and line-number metadata. */
    private function significantTokens(string $source): array
    {
        $tokens = array_filter(token_get_all($source, TOKEN_PARSE), static fn($token): bool => !is_array($token) || T_WHITESPACE !== $token[0]);

        return array_values(array_map(static fn($token) => is_array($token) ? [$token[0], $token[1]] : $token, $tokens));
    }

    /** Expresses each existing fixture indentation level using the consumer's native spacing. */
    private function withSpacing(string $source, string $indent, string $lineEnding): string
    {
        $source = preg_replace_callback('/^(?: {4})+/m', static fn(array $match): string => str_repeat($indent, intdiv(strlen($match[0]), 4)), $source);

        return str_replace("\n", $lineEnding, $source);
    }

    /** Writes explicitly selected trusted consumer configuration under the ignored fixture build directory. */
    private function configure(string $body): void
    {
        file_put_contents($this->directory . '/.build/ecs.php', '<?php return FastForward\\CodeStandards\\Config\\ECSConfig::configure(static function ($config): void { ' . $body . ' });');
    }

    /** @param list<string> $arguments Explicit user-visible CLI arguments. */
    private function cli(array $arguments): Process
    {
        return $this->process([PHP_BINARY, dirname(__DIR__, 2) . '/bin/code-standards', ...$arguments]);
    }

    /** @param list<string> $command Executable and separate arguments for this exclusive fixture. */
    private function process(array $command): Process
    {
        $process = new Process($command, $this->directory, ['TMPDIR' => $this->directory . '/.build/tmp'], timeout: 60);
        $process->run();

        return $process;
    }

    /** @param list<string> $arguments Git operations scoped exclusively to this synthetic repository. */
    private function git(array $arguments): void
    {
        (new Process(['git', ...$arguments], $this->directory, timeout: 30))->mustRun();
    }
}
