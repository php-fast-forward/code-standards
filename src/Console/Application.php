<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Filesystem\PhpFiles;
use FastForward\CodeStandards\Git\FileSelection;
use FastForward\CodeStandards\Process\NativeProcessRunner;
use FastForward\CodeStandards\Standards\StandardsRunner;

/** Thin CLI adapter; services own file selection and checking. */
final class Application
{
    /** Resolves ECS from the same Composer graph as the packaged binary. */
    public function __construct(private readonly string $ecs)
    {
    }

    /** @param list<string> $arguments Returns tool status, or 2 for invalid invocation. */
    public function run(array $arguments): int
    {
        try {
            $operation = array_shift($arguments);
            if (null === $operation || in_array($operation, ['help', '--help'], true)) {
                echo "code-standards check|fix (--all | --base=SHA --head=SHA) [--directory=PATH] [--config=PATH]\n";
                echo "check: PHP syntax and ECS dry check; fix: explicitly apply ECS fixes.\n";

                return 0;
            }
            if (!in_array($operation, ['check', 'fix'], true)) {
                throw new \InvalidArgumentException('Unknown operation. Use help.');
            }
            $options = [];
            foreach ($arguments as $argument) {
                if ('--all' === $argument) {
                    $key = 'all';
                    $value = true;
                } elseif (1 === preg_match('/\A--(base|head|directory|config)=(.+)\z/sD', $argument, $match)) {
                    [, $key, $value] = $match;
                } else {
                    throw new \InvalidArgumentException('Unsupported argument. Use help.');
                }
                if (array_key_exists($key, $options)) {
                    throw new \InvalidArgumentException('Duplicate option: ' . $key);
                }
                $options[$key] = $value;
            }
            $all = isset($options['all']);
            if ($all === (isset($options['base']) || isset($options['head']))) {
                throw new \InvalidArgumentException('Select exactly --all or both --base and --head.');
            }
            $directory = realpath($options['directory'] ?? getcwd());
            if (false === $directory || !is_dir($directory)) {
                throw new \InvalidArgumentException('Invalid project directory.');
            }
            $config = $options['config'] ?? dirname(__DIR__, 2) . '/config/ecs.php';
            if (!str_starts_with($config, '/')) {
                $config = $directory . '/' . $config;
            }
            if (!is_file($config)) {
                throw new \InvalidArgumentException('Configuration file does not exist.');
            }
            $process = new NativeProcessRunner();
            $paths = (new FileSelection($process))->select($directory, $options['base'] ?? null, $options['head'] ?? null, realpath($config));
            $files = (new PhpFiles())->filter($directory, $paths);
            printf("Selected %d PHP file(s).\n", count($files));

            return (new StandardsRunner($process, $this->ecs))->run($directory, $files, $config, 'fix' === $operation);
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");

            return 2;
        }
    }
}
