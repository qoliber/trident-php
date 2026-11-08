<?php

/** @internal A fixed protocol worker; never bootstrap an application. */
declare(strict_types=1);

namespace Qoliber\Trident\Http;

if (PHP_SAPI !== 'cli' || count($argv) !== 3 || $argv[1] !== (string) PHP_VERSION_ID || $argv[2] !== PHP_OS_FAMILY) {
    exit(66);
}
require __DIR__ . '/NativeResolver.php';
$input = stream_get_contents(STDIN, 1026);
if ($input === false || strlen($input) > 1025 || !str_ends_with($input, "\n")) {
    exit(65);
}
$host = substr($input, 0, -1);
if ($host === '' || preg_match('/[\x00-\x20\x7f]/', $host)) {
    exit(65);
}
try {
    $addresses = NativeResolver::lookup($host);
    if (count($addresses) > 128) {
        exit(65);
    }
    $result = json_encode(['protocol' => 1, 'php' => PHP_VERSION_ID, 'os' => PHP_OS_FAMILY, 'addresses' => $addresses], JSON_THROW_ON_ERROR);
    if (strlen($result) > 8192 || fwrite(STDOUT, $result) !== strlen($result)) {
        exit(65);
    }
} catch (\Throwable $e) {
    exit(65);
}
