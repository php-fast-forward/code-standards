<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TwigCsFixer\Config\ConfigResolver;

/** Fails an evaluation assertion; this harness is not a public Code Standards command. */
function verify(bool $condition, string $name): void
{
    if (!$condition) {
        throw new RuntimeException('Twig evaluation failed: ' . $name);
    }
    $GLOBALS['evaluation_checks'][] = $name;
}

/**
 * Starts only fixed local test tools with finite bounds and without inherited credentials.
 *
 * @param list<string> $arguments Actual executable and separate arguments; never shell fragments.
 * @param array<string, string|false> $environment Explicit synthetic-fixture environment.
 */
function process(array $arguments, string $directory, array $environment): Process
{
    $process = new Process($arguments, $directory, $environment, timeout: 20);
    $process->run();

    return $process;
}

/**
 * Captures hashes of owned source/configuration; generated test markers are deliberately separate.
 *
 * @return array<string, string> Relative fixture names mapped to exact byte hashes.
 */
function snapshot(string $directory): array
{
    $result = [];
    foreach (['composer.json', 'composer.lock', 'native-config.php', 'templates/keep a.twig', "templates/keep\nline.twig",
        'templates/excluded.twig', 'templates/unselected.twig', 'templates/gitignored.twig', 'templates/grammar.twig',
        'templates/custom.twig', '../outside.twig'] as $path) {
        $hash = hash_file('sha256', $directory . '/' . $path);
        if (false === $hash) {
            throw new RuntimeException('Cannot snapshot owned fixture: ' . $path);
        }
        $result[$path] = $hash;
    }

    return $result;
}

/**
 * Runs the native test worker; complete stdout must be native JSON, never a scraped human-output suffix.
 *
 * @param list<string> $selected Git-eligible paths; native configuration can narrow this scope.
 * @param array<string, string|false> $environment Explicit synthetic-fixture environment.
 */
function evaluate(string $autoload, string $directory, string $config, string $mode, array $selected, array $environment): Process
{
    $process = process([PHP_BINARY, __DIR__ . '/worker.php', $autoload, $directory, $config, $mode, ...$selected], $directory, $environment);
    if (in_array($process->getExitCode(), [0, 1], true)) {
        json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    return $process;
}

$GLOBALS['evaluation_checks'] = [];
$fixture = null;
$status = 0;
try {
    $autoload = getenv('TWIG_EVALUATION_AUTOLOAD');
    if (!is_string($autoload) || !str_starts_with($autoload, '/') || !is_file($autoload)) {
        throw new InvalidArgumentException('Provision the isolated Twig lock first and set TWIG_EVALUATION_AUTOLOAD to its absolute vendor/autoload.php; the evaluator never installs dependencies.');
    }
    $autoload = realpath($autoload);
    $repository = realpath(dirname(__DIR__, 3));
    $temporary = realpath(getenv('TWIG_EVALUATION_TEMP') ?: sys_get_temp_dir());
    if (false === $temporary || !is_dir($temporary) || str_starts_with($temporary . '/', $repository . '/')
        || str_starts_with($autoload, $repository . '/')) {
        throw new InvalidArgumentException('Evaluation runtime and temporary fixtures must be provisioned outside the checkout.');
    }
    require $autoload;

    $manifest = hash_file('sha256', __DIR__ . '/composer.json');
    $lock = hash_file('sha256', __DIR__ . '/composer.lock');
    $installed = json_decode(file_get_contents(dirname($autoload) . '/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $versions = [];
    foreach ($installed['packages'] as $package) {
        if (in_array($package['name'], ['vincentlanglet/twig-cs-fixer', 'twig/twig'], true)) {
            $versions[$package['name']] = ['version' => ltrim($package['version'], 'v'), 'source' => $package['source']['reference']];
        }
    }
    verify(($versions['vincentlanglet/twig-cs-fixer'] ?? null) === ['version' => '4.1.1', 'source' => '53c1234b6e5d87ac2beaf686774bba013697f9c5'], 'published-formatter-runtime');
    verify(($versions['twig/twig'] ?? null) === ['version' => '3.30.0', 'source' => '8c737079b726af72ff8ef3c595be9f6a810ea1ef'], 'published-parser-runtime');

    $fixture = $temporary . '/code-standards-twig-' . bin2hex(random_bytes(12));
    $directory = $fixture . '/consumer';
    $filesystem = new Filesystem();
    $filesystem->mkdir([$directory . '/templates', $directory . '/vendor', $directory . '/.runtime/tmp'], 0700);
    $environment = array_fill_keys(array_keys(getenv()), false);
    $environment['PATH'] = getenv('PATH') ?: '/usr/bin:/bin';
    $environment['HOME'] = $directory . '/.runtime';
    $environment['TMPDIR'] = $directory . '/.runtime/tmp';
    $environment['GIT_CONFIG_GLOBAL'] = '/dev/null';
    $environment['GIT_CONFIG_NOSYSTEM'] = '1';
    $git = static function (array $arguments) use ($directory, $environment): string {
        $process = process(['git', ...$arguments], $directory, $environment);
        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    };

    file_put_contents($directory . '/composer.json', '{"name":"fixture/twig-consumer"}');
    file_put_contents($directory . '/composer.lock', '{"packages":[]}');
    file_put_contents($directory . '/.gitignore', "/vendor/\n/.runtime/\n/templates/gitignored.twig\n");
    file_put_contents($directory . '/vendor/autoload.php', '<?php file_put_contents(__DIR__."/../autoload-executed", "unexpected");');
    foreach (['keep a.twig', "keep\nline.twig", 'excluded.twig', 'unselected.twig', 'gitignored.twig'] as $name) {
        file_put_contents($directory . '/templates/' . $name, "{{value}}\n");
    }
    file_put_contents($directory . '/templates/grammar.twig', "{% if true %}\n");
    file_put_contents($directory . '/templates/custom.twig', "{% fixture_mark %}{{ application_function() }}{% include 'not-present.twig' %}\n");
    file_put_contents($fixture . '/outside.twig', "{{outside}}\n");
    $config = file_get_contents(__DIR__ . '/fixtures/native-config.php');
    file_put_contents($directory . '/native-config.php', $config);
    $git(['init', '-q']);
    $git(['add', '--all']);
    $git(['add', '-f', '--', 'templates/gitignored.twig']);
    $ignored = array_fill_keys(array_filter(explode("\0", $git(['ls-files', '--cached', '--ignored', '--exclude-per-directory=.gitignore', '-z']))), true);
    $tracked = array_filter(explode("\0", $git(['ls-files', '-z'])));
    $selected = array_values(array_filter($tracked, static fn(string $path): bool => in_array($path, [
        'templates/keep a.twig', "templates/keep\nline.twig", 'templates/excluded.twig', 'templates/gitignored.twig',
    ], true) && !isset($ignored[$path])));
    verify(!in_array('templates/gitignored.twig', $selected, true), 'git-ignored-tracked-template-excluded');

    $before = snapshot($directory);
    $check = evaluate($autoload, $directory, 'native-config.php', 'check', $selected, $environment);
    verify(1 === $check->getExitCode(), 'native-style-failure:' . $check->getErrorOutput());
    verify(snapshot($directory) === $before, 'check-preserves-source-config-and-lock');
    $json = json_decode($check->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $reported = array_values(array_unique(array_column(array_column($json, 'location'), 'path')));
    verify($reported === ["templates/keep\nline.twig", 'templates/keep a.twig'], 'finder-intersection-sorted-and-deduplicated');
    $again = evaluate($autoload, $directory, 'native-config.php', 'check', $selected, $environment);
    verify($again->getOutput() === $check->getOutput() && 1 === $again->getExitCode(), 'native-json-byte-deterministic');
    verify(str_contains($check->getOutput(), 'keep\\nline.twig'), 'native-json-escapes-newline-path');

    // Deliberately reproduce the upstream path replacement instead of proposing that proxy as the adapter.
    $replaced = (new ConfigResolver($directory))->resolveConfig([$directory . '/templates/excluded.twig'], $directory . '/native-config.php', true);
    verify(count(iterator_to_array($replaced->getFinder())) === 1, 'cli-path-replacement-gap-reproduced');
    verify(null === $replaced->getCacheManager() && null === $replaced->getCacheFile(), 'native-cache-disabled');

    $override = str_replace('return $config;', '$config->getRuleset()->overrideRule(new TwigCsFixer\\Rules\\Delimiter\\DelimiterSpacingRule(beforeOverride: ["}}" => 0], afterOverride: ["{{" => 0])); return $config;', $config, $count);
    verify(1 === $count, 'known-native-fixture-override-point');
    file_put_contents($directory . '/override.php', $override);
    verify(0 === evaluate($autoload, $directory, 'override.php', 'check', $selected, $environment)->getExitCode(), 'native-rule-override-last');
    file_put_contents($directory . '/remove.php', str_replace('return $config;', '$config->getRuleset()->removeRule(TwigCsFixer\\Rules\\Delimiter\\DelimiterSpacingRule::class); return $config;', $config));
    verify(0 === evaluate($autoload, $directory, 'remove.php', 'check', $selected, $environment)->getExitCode(), 'native-rule-removal-last');
    verify(snapshot($directory) === $before, 'override-checks-do-not-write');

    verify(0 === evaluate($autoload, $directory, 'native-config.php', 'fix', $selected, $environment)->getExitCode(), 'explicit-native-fix');
    verify(file_get_contents($directory . '/templates/keep a.twig') === "{{ value }}\n", 'native-fixed-bytes');
    $fixed = snapshot($directory);
    verify(0 === evaluate($autoload, $directory, 'native-config.php', 'check', $selected, $environment)->getExitCode(), 'native-post-fix-check');
    verify(0 === evaluate($autoload, $directory, 'native-config.php', 'fix', $selected, $environment)->getExitCode() && snapshot($directory) === $fixed, 'second-fix-idempotent');
    foreach (['templates/excluded.twig', 'templates/unselected.twig', 'templates/gitignored.twig', '../outside.twig', 'composer.json', 'composer.lock', 'native-config.php'] as $path) {
        verify($fixed[$path] === $before[$path], 'outside-effective-selection-unchanged:' . $path);
    }

    file_put_contents($directory . '/templates/keep a.twig', "{{needs_fix}}\n");
    $grammarBefore = snapshot($directory);
    $grammar = evaluate($autoload, $directory, 'native-config.php', 'fix', ['templates/grammar.twig', 'templates/keep a.twig'], $environment);
    $syntax = json_decode($grammar->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    verify(1 === $grammar->getExitCode() && ($syntax[0]['severity'] ?? null) === 'critical', 'native-grammar-rejected-before-fix');
    verify(snapshot($directory) === $grammarBefore, 'syntax-failure-performs-no-fix');
    verify(0 === evaluate($autoload, $directory, 'native-config.php', 'check', ['templates/custom.twig'], $environment)->getExitCode(), 'custom-tag-function-and-include-parse');
    verify(is_file($directory . '/tag-parsed') && !file_exists($directory . '/function-called'), 'custom-parser-used-without-rendering');
    file_put_contents($directory . '/bare.php', '<?php $config = new TwigCsFixer\\Config\\Config(); $config->setFinder((new TwigCsFixer\\File\\Finder())->in(__DIR__."/templates")); return $config;');
    verify(1 === evaluate($autoload, $directory, 'bare.php', 'check', ['templates/custom.twig'], $environment)->getExitCode(), 'unregistered-custom-tag-rejected');
    verify(!file_exists($directory . '/autoload-executed') && !file_exists($directory . '/.twig-cs-fixer.cache'), 'no-implicit-consumer-autoload-or-project-cache');

    $missing = process([PHP_BINARY, __FILE__], $directory, $environment);
    verify(2 === $missing->getExitCode() && str_contains($missing->getErrorOutput(), 'never installs dependencies'), 'missing-provisioned-loader-fails-objectively');
    verify($manifest === hash_file('sha256', __DIR__ . '/composer.json') && $lock === hash_file('sha256', __DIR__ . '/composer.lock'), 'evaluation-does-not-change-its-dependency-inputs');
    echo json_encode(['result' => 'passed', 'checks' => count($GLOBALS['evaluation_checks']), 'versions' => $versions], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    $status = 2;
} finally {
    if (null !== $fixture) {
        (new Filesystem())->remove($fixture);
    }
}

exit($status);
