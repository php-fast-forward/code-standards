<?php

declare(strict_types=1);

require __DIR__ . '/support.php';

if (3 !== $argc || !is_file($argv[1]) || !is_file($argv[2]) || is_link($argv[1]) || is_link($argv[2])) {
    throw new RuntimeException('Usage: php scripts/phar/compare.php FRESH_BUILD COMMITTED_EXECUTABLE');
}
$directory = sys_get_temp_dir() . '/standards-phar-compare-' . bin2hex(random_bytes(12));
mkdir($directory, 0700);
register_shutdown_function(static fn() => removeTree($directory));
// Open executable bytes as data. Never include, map or execute the committed candidate.
copy($argv[1], $directory . '/fresh.phar');
copy($argv[2], $directory . '/committed.phar');
$fresh = new Phar($directory . '/fresh.phar');
$committed = new Phar($directory . '/committed.phar');
if ($fresh->getStub() !== $committed->getStub()) {
    throw new RuntimeException('Committed PHAR stub differs from the fresh build.');
}
$contents = static function (Phar $archive): array {
    if (!$archive->isFileFormat(Phar::PHAR) || $archive->hasMetadata()) {
        throw new RuntimeException('Expected a native PHAR without serialized metadata.');
    }
    $prefix = 'phar://' . $archive->getPath() . '/';
    $entries = [];
    foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST) as $entry) {
        if ($entry->isLink() || $entry->hasMetadata()) {
            throw new RuntimeException('PHAR entries must not contain links or serialized metadata.');
        }
        $name = substr($entry->getPathname(), strlen($prefix));
        $hash = $entry->isDir() ? 'directory' : hash_file('sha256', $entry->getPathname());
        if (false === $hash) {
            throw new RuntimeException('Cannot read a PHAR entry.');
        }
        $entries[$name] = $hash;
    }
    ksort($entries);

    return $entries;
};
$expected = $contents($fresh);
$actual = $contents($committed);
if ($expected !== $actual) {
    $different = array_keys(array_diff_assoc($expected, $actual) + array_diff_assoc($actual, $expected));
    throw new RuntimeException('Committed PHAR contents differ from the fresh build: ' . json_encode(array_slice($different, 0, 10), JSON_THROW_ON_ERROR));
}
fwrite(STDOUT, "Committed PHAR stub and complete contents match the fresh build.\n");
