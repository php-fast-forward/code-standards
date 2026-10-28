<?php

declare(strict_types=1);

// This trusted, unprivileged bootstrap is entered through setsid, before any selected consumer configuration.
$control = fopen('php://fd/3', 'w');
if (false === $control || strlen((string) getmypid()) + 1 !== fwrite($control, getmypid() . "\n")) {
    exit(1);
}
fflush($control);
if ('S' !== fread(STDIN, 1)) {
    exit(1);
}
fclose($control);
$arguments = array_slice($argv, 1);
if ([] === $arguments) {
    exit(1);
}
$process = proc_open($arguments, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
if (!is_resource($process)) {
    exit(1);
}
exit(proc_close($process));
