<?php

$root = dirname(__DIR__);
$excluded = [DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failures = [];

foreach ($files as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php') {
        continue;
    }

    foreach ($excluded as $fragment) {
        if (str_contains($path, $fragment)) {
            continue 2;
        }
    }

    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $code);
    if ($code !== 0) {
        $failures[] = $path . PHP_EOL . implode(PHP_EOL, $output);
    }
    $output = [];
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL . PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'PHP syntax OK' . PHP_EOL;