<?php

/*
 * Runs Pest with code coverage and the 100% gate.
 *
 * Uses a loaded coverage driver when there is one. Otherwise it looks for the Xdebug
 * build that Laravel Herd ships, and loads it for this run only (php.ini stays untouched).
 */

$args = array_slice($argv, 1) ?: ['--coverage', '--min=100'];
$flags = ['-d', 'xdebug.mode=coverage'];

if (! extension_loaded('xdebug') && ! extension_loaded('pcov')) {
    $home = getenv('USERPROFILE') ?: getenv('HOME');
    $dll = sprintf('%s/.config/herd/bin/xdebug/xdebug-%d.%d.dll', $home, PHP_MAJOR_VERSION, PHP_MINOR_VERSION);

    if (! is_file($dll)) {
        fwrite(STDERR, "No coverage driver found. Install Xdebug or PCOV, or run inside Herd.\n");
        exit(1);
    }

    array_push($flags, '-d', 'zend_extension='.$dll);
}

$command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, ...$flags, 'vendor/bin/pest', ...$args]));
passthru($command, $exitCode);
exit($exitCode);
