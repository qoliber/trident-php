<?php

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

/**
 * The optional allowlist of Trident API hosts (1.7.0): an operator lists the
 * hosts (`host` or `host:port`) the shop may talk to, and every other
 * configured instance is dropped with a reason. Exact matches only — no
 * suffix or prefix matching. An empty list allows everything.
 */
final class ApiHostAllowlist
{
    /**
     * @param list<string>|string $allowed A list, or a comma/space separated string (e.g. an env var).
     * @return list<string>
     */
    public static function parse(array|string $allowed): array
    {
        $items = is_string($allowed) ? (preg_split('/[\s,]+/', $allowed) ?: []) : $allowed;

        return array_values(array_filter(array_map(static fn ($v): string => self::normalise((string) $v), $items), static fn (string $v): bool => $v !== ''));
    }

    /**
     * `host` or `host:port`, lower case, without IPv6 brackets or a trailing
     * dot on the host: `[::1]:9301` → `::1:9301` is ambiguous, so an IPv6
     * entry keeps its brackets when it has a port (`[::1]:9301`) and is bare
     * otherwise (`::1`).
     */
    public static function normalise(string $entry): string
    {
        $entry = strtolower(trim($entry));
        if ($entry === '') {
            return '';
        }
        if (preg_match('/^\[([0-9a-f:.]+)\](?::(\d+))?$/', $entry, $m) === 1) {
            return isset($m[2]) ? '[' . $m[1] . ']:' . $m[2] : $m[1];
        }
        if (preg_match('/^([^:]+?)\.?(?::(\d+))?$/', $entry, $m) === 1) {
            return $m[1] . (isset($m[2]) ? ':' . $m[2] : '');
        }

        return $entry;
    }

    /**
     * @param list<Instance>      $instances
     * @param list<string>|string $allowed
     * @return array{0: list<Instance>, 1: list<string>} the kept instances and one error per dropped one
     */
    public static function filter(array $instances, array|string $allowed, string $label = 'the allowed API hosts'): array
    {
        $allowed = self::parse($allowed);
        if ($allowed === []) {
            return [array_values($instances), []];
        }
        $kept = [];
        $errors = [];
        foreach ($instances as $instance) {
            $host = self::normalise((string) parse_url($instance->apiUrl, PHP_URL_HOST));
            $port = parse_url($instance->apiUrl, PHP_URL_PORT);
            $withPort = $port === null ? null : (str_contains($host, ':') ? '[' . $host . ']' : $host) . ':' . $port;
            if (in_array($host, $allowed, true) || ($withPort !== null && in_array($withPort, $allowed, true))) {
                $kept[] = $instance;
            } else {
                $errors[] = sprintf('%s: %s is not in %s', $instance->name, $instance->apiUrl, $label);
            }
        }

        return [$kept, $errors];
    }
}
