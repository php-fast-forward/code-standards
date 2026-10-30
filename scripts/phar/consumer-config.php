<?php

declare(strict_types=1);

/** Exercises real Composer autoload/autoload-dev and a local development package through the selected executable. */
function verifyConsumerRules(string $executable, array $environment): array
{
    $directory = $environment['TMPDIR'] . '/consumer-rules-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    register_shutdown_function(static fn() => removeTree($directory));
    $template = file_get_contents(dirname(__DIR__, 2) . '/tests/Fixtures/BootstrapFixer.php.inc');
    $rules = [
        'FixtureRuntime' => 'runtime-rules',
        'FixtureDevelopment' => 'development-rules',
        'FixturePackage' => 'package/src',
    ];
    foreach ($rules as $namespace => $path) {
        mkdir($directory . '/' . $path, 0755, true);
        file_put_contents($directory . '/' . $path . '/BootstrapFixer.php', str_replace(
            ['CodeStandardsFixture', 'Fixture/replace_marker', 'needs-fix', "'fixed'"],
            [$namespace, $namespace . '/replace_marker', $namespace . '-before', "'" . $namespace . "-after'"],
            $template,
        ));
    }
    writeJson($directory . '/package/composer.json', [
        'name' => 'fixture/development-rules',
        'version' => '1.0.0',
        'autoload' => ['psr-4' => ['FixturePackage\\' => 'src/']],
    ]);
    writeJson($directory . '/composer.json', [
        'name' => 'fixture/consumer-rules',
        'repositories' => [['type' => 'path', 'url' => 'package', 'options' => ['symlink' => false]], ['packagist.org' => false]],
        'require-dev' => ['fixture/development-rules' => '1.0.0'],
        'autoload' => ['psr-4' => ['FixtureRuntime\\' => 'runtime-rules/']],
        'autoload-dev' => ['psr-4' => ['FixtureDevelopment\\' => 'development-rules/']],
    ]);
    runCommand(['composer', 'update', '--no-plugins', '--no-scripts', '--no-interaction', '--no-progress', '--no-audit'], $directory, environment: $environment);
    $installed = json_decode(file_get_contents($directory . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    if (!in_array('fixture/development-rules', $installed['dev-package-names'], true)) {
        throw new RuntimeException('Fixture rule package was not installed as a development dependency.');
    }
    $source = "<?php\n\ndeclare(strict_types=1);\n\n";
    foreach (array_keys($rules) as $namespace) {
        $source .= "echo '" . $namespace . "-before';\n";
    }
    file_put_contents($directory . '/source.php', $source);
    file_put_contents($directory . '/ecs.php', <<<'CONFIG'
<?php

require __DIR__ . '/vendor/autoload.php';
file_put_contents(__DIR__ . '/config-executed', 'explicit');

return FastForward\CodeStandards\Config\ECSConfig::configure()->withRules([
    FixtureRuntime\BootstrapFixer::class,
    FixtureDevelopment\BootstrapFixer::class,
    FixturePackage\BootstrapFixer::class,
]);
CONFIG);
    runCommand(['git', 'init', '-q'], $directory, environment: $environment);
    runCommand(['git', 'add', 'source.php'], $directory, environment: $environment);
    $metadata = ['composer.json', 'composer.lock', 'vendor/autoload.php', 'ecs.php'];
    $before = array_map(static fn(string $path) => hash_file('sha256', $directory . '/' . $path), $metadata);
    runCommand([PHP_BINARY, $executable, 'check', '--all'], $directory, environment: $environment);
    if (file_exists($directory . '/config-executed')) {
        throw new RuntimeException('Consumer ECS configuration was implicitly discovered.');
    }
    $output = runCommand([PHP_BINARY, $executable, 'check', '--all', '--config=ecs.php'], $directory, 2, $environment);
    if ($source !== file_get_contents($directory . '/source.php') || !is_file($directory . '/config-executed')) {
        throw new RuntimeException('Explicit consumer configuration did not run read-only.');
    }
    foreach (array_keys($rules) as $namespace) {
        if (!str_contains($output, $namespace . '-after')) {
            throw new RuntimeException('Consumer rule did not detect its fixture: ' . $namespace);
        }
    }
    runCommand([PHP_BINARY, $executable, 'fix', '--all', '--config=ecs.php'], $directory, environment: $environment);
    $fixed = file_get_contents($directory . '/source.php');
    foreach (array_keys($rules) as $namespace) {
        if (!str_contains($fixed, "'" . $namespace . "-after'") || str_contains($fixed, $namespace . '-before')) {
            throw new RuntimeException('Consumer rule did not fix its fixture: ' . $namespace);
        }
    }
    runCommand([PHP_BINARY, $executable, 'check', '--all', '--config=ecs.php'], $directory, environment: $environment);
    if ($fixed !== file_get_contents($directory . '/source.php') || $before !== array_map(static fn(string $path) => hash_file('sha256', $directory . '/' . $path), $metadata)) {
        throw new RuntimeException('Consumer verification changed source or Composer/configuration inputs.');
    }

    return ['autoload' => true, 'autoload_dev' => true, 'require_dev_rule' => true, 'check_exit' => 2, 'fix_exit' => 0, 'recheck_exit' => 0];
}
