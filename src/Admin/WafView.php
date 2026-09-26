<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

/**
 * The part of an instance's WAF export (`GET /admin/denoisers/export/waf`)
 * and learned query scopes that belongs to ONE shop.
 *
 * The export is global: on a Trident shared by several sites it lists every
 * site's dead zones. A shop's screen shows its own host(s) plus the `*` rows,
 * which apply to every host. Keys are `host|path_prefix`.
 */
final class WafView
{
    /**
     * @param array<string, mixed> $export the decoded WAF export
     * @param list<string>         $hosts  this shop's hosts as Trident keys them (`host[:port]`)
     *
     * @return list<array{host: string, prefix: string, action: string, inert: bool}>
     */
    public static function deadZones(array $export, array $hosts): array
    {
        $out = [];
        foreach ((array) ($export['dead_zones'] ?? []) as $row) {
            if (!is_array($row) || !is_string($row['zone_key'] ?? null)) {
                continue;
            }
            $key = self::split($row['zone_key']);
            if ($key === null || !self::ours($key[0], $hosts)) {
                continue;
            }
            $out[] = [
                'host' => $key[0],
                'prefix' => $key[1],
                'action' => is_string($row['action'] ?? null) ? $row['action'] : '',
                'inert' => $key[0] === '*',
            ];
        }

        return $out;
    }

    /**
     * Learned noise parameters of this shop's query scopes. The export's own
     * `query_noise_params` carries no host and cannot be filtered, so the
     * scopes (`GET /admin/denoisers/query/scopes`) are the source.
     *
     * @param list<mixed>  $scopes
     * @param list<string> $hosts
     *
     * @return list<array{param: string, scope: string, inert: bool}>
     */
    public static function noise(array $scopes, array $hosts): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope) || !is_string($scope['scope_key'] ?? null) || !is_array($scope['noise'] ?? null)) {
                continue;
            }
            $key = self::split($scope['scope_key']);
            if ($key === null || !self::ours($key[0], $hosts)) {
                continue;
            }
            foreach ($scope['noise'] as $param) {
                if (is_string($param)) {
                    $out[] = ['param' => $param, 'scope' => $scope['scope_key'], 'inert' => $key[0] === '*'];
                }
            }
        }
        usort($out, static fn (array $a, array $b): int => [$a['param'], $a['scope']] <=> [$b['param'], $b['scope']]);

        return $out;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function split(string $key): ?array
    {
        $bar = strpos($key, '|');

        return $bar === false ? null : [substr($key, 0, $bar), substr($key, $bar + 1)];
    }

    /**
     * @param list<string> $hosts
     */
    private static function ours(string $rowHost, array $hosts): bool
    {
        if ($rowHost === '*') {
            return true;
        }
        foreach ($hosts as $host) {
            if (strtolower($rowHost) === strtolower($host)) {
                return true;
            }
        }

        return false;
    }
}
