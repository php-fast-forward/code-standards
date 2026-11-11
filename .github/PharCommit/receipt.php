<?php

declare(strict_types=1);

if (6 !== $argc || !is_file($argv[1]) || is_link($argv[1]) || !preg_match('/^[a-f0-9]{40}$/D', $argv[2])) {
    throw new RuntimeException('Usage: receipt.php EXECUTABLE SOURCE_SHA RUN_ID RUN_ATTEMPT OUTPUT');
}
foreach ([$argv[3], $argv[4]] as $number) {
    if (!preg_match('/^[1-9][0-9]*$/D', $number) || strlen($number) > 15) {
        throw new RuntimeException('Run identity must contain positive safe integers.');
    }
}
$hash = hash_file('sha256', $argv[1]);
if (false === $hash) {
    throw new RuntimeException('Cannot hash the executable.');
}
$receipt = [
    'version' => 1,
    'source_sha' => $argv[2],
    'run_id' => (int) $argv[3],
    'run_attempt' => (int) $argv[4],
    'sha256' => $hash,
];
if (false === file_put_contents($argv[5], json_encode($receipt, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n")) {
    throw new RuntimeException('Cannot write the build receipt.');
}
