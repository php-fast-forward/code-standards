<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Config\ConfigResolver;
use TwigCsFixer\Environment\StubbedEnvironment;
use TwigCsFixer\Report\Report;
use TwigCsFixer\Report\Reporter\GitlabReporter;
use TwigCsFixer\Report\Violation;
use TwigCsFixer\Report\ViolationId;
use TwigCsFixer\Runner\Fixer;
use TwigCsFixer\Runner\Linter;
use TwigCsFixer\Token\Tokenizer;

/**
 * Materializes the native Finder result before intersecting all entries, including appended iterables.
 *
 * @param list<string> $paths Git-eligible template paths inside the owned fixture.
 * @return list<SplFileInfo> Sorted, deduplicated native file inputs.
 */
function selectedFiles(Config $config, array $paths, string $directory): array
{
    $selected = [];
    foreach ($paths as $path) {
        $real = realpath($path);
        if (false === $real || !is_file($real) || is_link($path) || !str_starts_with($real, $directory . '/')) {
            throw new InvalidArgumentException('Expected readable owned template paths inside the fixture.');
        }
        $selected[$real] = true;
    }

    $files = [];
    foreach ($config->getFinder() as $file) {
        $real = $file->getRealPath();
        if (false !== $real && isset($selected[$real])) {
            $relative = substr($real, strlen($directory) + 1);
            $files[$relative] = new SplFileInfo($relative);
        }
    }
    ksort($files, SORT_STRING);

    return array_values($files);
}

/**
 * Parses with explicitly registered native extensions; never compiles, renders or discovers an application.
 *
 * @param list<SplFileInfo> $files Effective native Finder/Git intersection.
 */
function syntaxReport(Config $config, array $files): Report
{
    $environment = new Environment(new ArrayLoader(), ['cache' => false]);
    foreach ($config->getTwigExtensions() as $extension) {
        $environment->addExtension($extension);
    }
    foreach ($config->getTokenParsers() as $parser) {
        $environment->addTokenParser($parser);
    }
    foreach ($config->getNodeVisitors() as $visitor) {
        $environment->addNodeVisitor($visitor);
    }

    $report = new Report($files);
    foreach ($files as $file) {
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            throw new RuntimeException('Cannot read selected fixture template.');
        }
        try {
            $source = new Source($content, $file->getPathname());
            $environment->parse($environment->tokenize($source));
        } catch (Error $error) {
            $report->addViolation(new Violation(
                Violation::LEVEL_FATAL,
                $error->getRawMessage(),
                $file->getPathname(),
                identifier: new ViolationId(line: $error->getTemplateLine()),
            ));
        }
    }

    return $report;
}

// Fixed test-worker arguments: preinstalled autoloader, owned fixture, native config, mode and selected paths.
try {
    if ($argc < 6 || !in_array($argv[4], ['check', 'fix'], true) || !is_file($argv[1])) {
        throw new InvalidArgumentException('Twig evaluation worker requires its provisioned runtime and owned fixture arguments.');
    }
    require $argv[1];
    $directory = realpath($argv[2]);
    if (false === $directory || !is_dir($directory) || !chdir($directory)) {
        throw new InvalidArgumentException('Cannot enter the owned Twig fixture directory.');
    }

    $config = (new ConfigResolver($directory))->resolveConfig([], $argv[3], true);
    $files = selectedFiles($config, array_slice($argv, 5), $directory);
    $report = syntaxReport($config, $files);
    if (0 === $report->getTotalErrors()) {
        $environment = new StubbedEnvironment($config->getTwigExtensions(), $config->getTokenParsers(), $config->getNodeVisitors());
        $tokenizer = new Tokenizer($environment);
        $report = (new Linter($environment, $tokenizer, $config->getCacheManager()))->run(
            $files,
            $config->getRuleset(),
            'fix' === $argv[4] ? new Fixer($tokenizer) : null,
        );
    }

    $output = new BufferedOutput();
    // Native reporter instances retain fingerprint collision state; each result gets a fresh one.
    (new GitlabReporter())->display($output, $report, null, false);
    echo $output->fetch();
    exit(0 === $report->getTotalErrors() ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Twig evaluation worker: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
