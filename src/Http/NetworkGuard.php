<?php

declare(strict_types=1);

namespace Qoliber\Trident\Http;

/**
 * Addresses a Trident API URL may never reach, whatever the configuration:
 * link-local ranges and the cloud metadata services (instance credentials).
 * Private ranges are allowed — Trident normally IS internal; restrict further
 * with TRIDENT_ALLOWED_API_HOSTS.
 *
 * Checked on the RESOLVED address at request time, and the checked address is
 * the one used ({@see TransportFactory} pins it), so DNS rebinding cannot swap it.
 */
final class NetworkGuard
{
    public const FORBIDDEN = ['169.254.0.0/16', '0.0.0.0/8', 'fe80::/10', 'fd00:ec2::254/128', '100.100.100.200/32', '::/128'];
    public const FORBIDDEN_NAMES = ['metadata.google.internal', 'metadata'];

    private readonly \Closure $resolve;

    /**
     * @param (callable(string): list<string>)|null $resolve host => addresses (tests pass a fake)
     */
    public function __construct(?callable $resolve = null)
    {
        $this->resolve = $resolve !== null ? \Closure::fromCallable($resolve) : static function (string $host): array {
            $v4 = gethostbynamel($host) ?: [];
            $v6 = [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $v6[] = (string) $record['ipv6'];
                }
            }

            return array_values(array_merge($v4, $v6));
        };
    }

    /**
     * The address to connect to, or an exception saying why not.
     */
    public function address(string $host): string
    {
        $host = strtolower(trim($host, '[]'));
        if (in_array(rtrim($host, '.'), self::FORBIDDEN_NAMES, true)) {
            throw new \RuntimeException(sprintf('%s is a cloud metadata service: refused', $host));
        }
        // Numeric hosts in any spelling libc accepts (2852039166, 0251.0376.0251.0376,
        // 0xa9fea9fe) are addresses, not names: never hand them to the resolver.
        $numeric = self::numericV4($host);
        $addresses = @inet_pton($host) !== false ? [$host] : ($numeric !== null ? [$numeric] : ($this->resolve)($host));
        if ($addresses === []) {
            throw new \RuntimeException(sprintf('%s does not resolve', $host));
        }
        foreach ($addresses as $address) {
            if (self::forbidden($address)) {
                throw new \RuntimeException(sprintf('%s resolves to %s (link-local / cloud metadata): refused', $host, $address));
            }
        }

        return $addresses[0];
    }

    /**
     * inet_aton spelling of an IPv4 address (1–4 parts, each decimal, 0x hex or
     * 0 octal), as dotted quad; null when the host is not one.
     */
    public static function numericV4(string $host): ?string
    {
        if (preg_match('/^(0x[0-9a-f]+|[0-9]+)(\.(0x[0-9a-f]+|[0-9]+)){0,3}$/i', $host) !== 1) {
            return null;
        }
        $parts = [];
        foreach (explode('.', $host) as $part) {
            $v = match (true) {
                str_starts_with(strtolower($part), '0x') => hexdec(substr($part, 2)),
                strlen($part) > 1 && $part[0] === '0' => preg_match('/^[0-7]+$/', $part) === 1 ? octdec($part) : null,
                default => (int) $part,
            };
            if ($v === null || !is_int($v)) {
                return null;
            }
            $parts[] = $v;
        }
        $n = count($parts);
        $last = array_pop($parts);
        $limit = [1 => 0xFFFFFFFF, 2 => 0xFFFFFF, 3 => 0xFFFF, 4 => 0xFF][$n];
        if ($last > $limit) {
            return null;
        }
        foreach ($parts as $p) {
            if ($p > 0xFF) {
                return null;
            }
        }
        $value = $last;
        foreach ($parts as $i => $p) {
            $value |= $p << (24 - 8 * $i);
        }

        return long2ip($value);
    }

    public static function forbidden(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return true;
        }
        // IPv6 forms that carry an IPv4 address are judged by that address:
        // v4-mapped ::ffff:a.b.c.d, v4-compatible ::a.b.c.d (not ::1, the
        // loopback), NAT64 64:ff9b::a.b.c.d, 6to4 2002:AABB:CCDD::.
        if (strlen($packed) === 16) {
            if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")
                || str_starts_with($packed, "\x00\x64\xff\x9b" . str_repeat("\0", 8))) {
                $packed = substr($packed, 12);
            } elseif (str_starts_with($packed, str_repeat("\0", 12)) && $packed !== str_repeat("\0", 15) . "\x01" && $packed !== str_repeat("\0", 16)) {
                $packed = substr($packed, 12);
            } elseif (str_starts_with($packed, "\x20\x02")) {
                if (self::forbidden((string) inet_ntop(substr($packed, 2, 4)))) {
                    return true;
                }
            }
        }
        foreach (self::FORBIDDEN as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $n = inet_pton($net);
            if ($n === false || strlen($n) !== strlen($packed)) {
                continue;
            }
            $bits = (int) $bits;
            $bytes = intdiv($bits, 8);
            if (strncmp($packed, $n, $bytes) !== 0) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0 || (ord($packed[$bytes]) & ((0xFF << (8 - $rest)) & 0xFF)) === (ord($n[$bytes]) & ((0xFF << (8 - $rest)) & 0xFF))) {
                return true;
            }
        }

        return false;
    }
}
