<?php

declare(strict_types=1);

namespace Qoliber\Trident\Http;

/**
 * Native A/NSS + AAAA resolution in a trusted, disposable CLI process.
 *
 * @internal Finite hostname deadlines require proc_* and a matching trusted
 * CLI. No shell, application bootstrap, URL, token or request body is passed.
 * Windows pipes are not qualified for nonblocking use and are refused.
 */
final class NativeResolver
{
    private const MAX_OUTPUT = 8192;

    /** The existing resolver semantics, also retained for explicit unlimited work. @return list<string> */
    public static function lookup(string $host): array
    {
        $v4 = gethostbynamel($host) ?: [];
        $v6 = [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $v6[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_merge($v4, $v6));
    }

    /** @return list<string> */
    public function resolve(string $host, float $deadline): array
    {
        if (!is_finite($deadline) || $deadline <= self::now() + 0.001) {
            throw new NativeResolutionException('Native DNS deadline exhausted');
        }
        foreach (['proc_open', 'proc_get_status', 'proc_terminate', 'proc_close',
                  'stream_select', 'stream_set_blocking', 'gethostbynamel', 'dns_get_record'] as $function) {
            if (!function_exists($function)) {
                throw new NativeResolutionException('Bounded native DNS requires enabled ' . $function);
            }
        }
        if (PHP_OS_FAMILY === 'Windows') {
            throw new NativeResolutionException('Bounded native DNS requires qualified nonblocking process pipes; Windows is not supported by this transport path');
        }
        if ($host === '' || strlen($host) > 1024 || preg_match('/[\x00-\x20\x7f]/', $host)) {
            throw new NativeResolutionException('Native DNS hostname is invalid');
        }
        $binaries = PHP_SAPI === 'cli' ? [PHP_BINARY] : [
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            PHP_BINDIR . DIRECTORY_SEPARATOR . 'php',
        ];
        foreach (array_unique($binaries) as $binary) {
            if (!str_starts_with($binary, DIRECTORY_SEPARATOR) || !is_file($binary) || !is_executable($binary)) {
                continue;
            }
            try {
                return $this->resolveWithBinary($host, $deadline, $binary);
            } catch (NativeResolutionException $e) {
                // Exit 66 is emitted before reading the hostname or invoking NSS.
                // Never retry DNS errors/timeouts with a second interpreter.
                if ($e->getCode() !== 66) {
                    throw $e;
                }
            }
        }
        throw new NativeResolutionException('Bounded native DNS requires a trusted matching PHP CLI in PHP_BINDIR');
    }

    /** @return list<string> */
    private function resolveWithBinary(string $host, float $deadline, string $binary): array
    {
        if ($deadline <= self::now() + 0.001) {
            throw new NativeResolutionException('Native DNS deadline exhausted');
        }
        // Reserve part of this same deadline for killing/reaping a stuck lookup.
        $workDeadline = $deadline - min(0.05, ($deadline - self::now()) * 0.1);
        $pipes = [];
        $process = @proc_open([
            $binary, '-n', __DIR__ . '/native-resolver-worker.php',
            (string) PHP_VERSION_ID, PHP_OS_FAMILY,
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new NativeResolutionException('Bounded native DNS could not start its trusted PHP CLI');
        }
        $status = null;
        $output = '';
        $errors = '';
        $input = $host . "\n";
        $sent = 0;
        $inputFailed = false;
        try {
            foreach ($pipes as $pipe) {
                if (!stream_set_blocking($pipe, false)) {
                    throw new NativeResolutionException('Bounded native DNS requires nonblocking process pipes');
                }
            }
            while (true) {
                if (self::now() >= $workDeadline) {
                    throw new NativeResolutionException('Native DNS deadline exhausted');
                }
                if (isset($pipes[0])) {
                    $written = @fwrite($pipes[0], substr($input, $sent));
                    if ($written === false) {
                        // A mismatching CLI may already have exited 66 without
                        // reading stdin. Classify its terminal status before
                        // deciding whether a different fixed CLI may be tried.
                        $inputFailed = true;
                    } else {
                        $sent += $written;
                    }
                    if ($inputFailed || $sent === strlen($input)) {
                        fclose($pipes[0]);
                        unset($pipes[0]);
                    }
                }
                foreach ([1, 2] as $index) {
                    $bytes = @stream_get_contents($pipes[$index], self::MAX_OUTPUT + 1);
                    if ($bytes === false) {
                        throw new NativeResolutionException('Native DNS worker output failed');
                    }
                    if ($index === 1) {
                        $output .= $bytes;
                    } else {
                        $errors .= $bytes;
                    }
                }
                if (strlen($output) > self::MAX_OUTPUT || strlen($errors) > 1024) {
                    throw new NativeResolutionException('Native DNS worker output exceeds its bound');
                }
                $status = proc_get_status($process);
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                    break;
                }
                // Keep the first terminal status: PHP 8.1 does not cache its exit code.
                if (!$status['running']) {
                    $output .= (string) stream_get_contents($pipes[1], self::MAX_OUTPUT + 1);
                    $errors .= (string) stream_get_contents($pipes[2], 1025);
                    break;
                }
                $read = [$pipes[1], $pipes[2]];
                $write = isset($pipes[0]) ? [$pipes[0]] : [];
                $except = [];
                $wait = max(0, min(10000, (int) (($workDeadline - self::now()) * 1000000)));
                if (@stream_select($read, $write, $except, 0, $wait) === false) {
                    throw new NativeResolutionException('Native DNS worker wait failed');
                }
            }
            if ($status['exitcode'] === 66) {
                throw new NativeResolutionException('Native DNS worker PHP CLI does not match this runtime', 66);
            }
            if ($inputFailed) {
                throw new NativeResolutionException('Native DNS worker input failed');
            }
            if ($status['exitcode'] !== 0 || $errors !== '' || strlen($output) > self::MAX_OUTPUT) {
                throw new NativeResolutionException('Native DNS worker failed or its PHP CLI does not match this runtime');
            }
            try {
                $answer = json_decode($output, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new NativeResolutionException('Native DNS worker returned an invalid response', 0, $e);
            }
            if (!is_array($answer) || array_keys($answer) !== ['protocol', 'php', 'os', 'addresses']
                || $answer['protocol'] !== 1 || $answer['php'] !== PHP_VERSION_ID || $answer['os'] !== PHP_OS_FAMILY
                || !is_array($answer['addresses']) || !array_is_list($answer['addresses']) || count($answer['addresses']) > 128) {
                throw new NativeResolutionException('Native DNS worker returned an invalid response');
            }
            foreach ($answer['addresses'] as $address) {
                if (!is_string($address) || @inet_pton($address) === false) {
                    throw new NativeResolutionException('Native DNS worker returned an invalid address');
                }
            }

            return $answer['addresses'];
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $status ??= proc_get_status($process);
            if ($status['running']) {
                @proc_terminate($process, 15);
                $termDeadline = min($deadline, self::now() + 0.005);
                do {
                    $status = proc_get_status($process);
                    if (!$status['running']) {
                        break;
                    }
                    usleep(500);
                } while (self::now() < $termDeadline);
                if ($status['running']) {
                    @proc_terminate($process, 9);
                    do {
                        $status = proc_get_status($process);
                        if (!$status['running']) {
                            break;
                        }
                        usleep(500);
                    } while (self::now() < $deadline);
                }
            }
            if ($status['running']) {
                // Never call blocking proc_close on a live child. Resource GC uses
                // waitpid(WNOHANG); the failure is explicit rather than a hidden wait.
                throw new NativeResolutionException('Native DNS worker could not be retired within the request deadline');
            }
            proc_close($process);
        }
    }

    private static function now(): float
    {
        return hrtime(true) / 1000000000;
    }
}
