<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Esi;

/**
 * Signed fragment URLs.
 *
 * A fragment endpoint renders what its URL says. Unsigned, anyone could ask
 * it to render arbitrary arguments — filling the cache with variants, or
 * rendering things the page never showed. The parameters are encoded
 * URL-safe (base64url JSON) and signed with an HMAC of the platform's secret.
 */
final class FragmentUrl
{
    public function __construct(private readonly string $secret)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('FragmentUrl needs a non-empty secret');
        }
    }

    /**
     * @param string               $base   Root-relative base, e.g. `/`.
     * @param string               $param  Query parameter naming the fragment, e.g. `trident-esi`.
     * @param string               $type   Fragment type.
     * @param array<string, mixed> $args   JSON-encodable arguments.
     */
    public function build(string $base, string $param, string $type, array $args = []): string
    {
        $encoded = self::encode($args);
        $query = http_build_query([$param => $type, 'a' => $encoded, 's' => $this->sign($type . '|' . $encoded)]);
        return rtrim($base, '?') . (str_contains($base, '?') ? '&' : '?') . $query;
    }

    /**
     * @param string $type    From the request.
     * @param string $encoded The `a` parameter.
     * @param string $sig     The `s` parameter.
     * @return array<string, mixed>|null Arguments, or null when the signature does not match.
     */
    public function verify(string $type, string $encoded, string $sig): ?array
    {
        if (!hash_equals($this->sign($type . '|' . $encoded), $sig)) {
            return null;
        }
        return self::decode($encoded);
    }

    public function sign(string $data): string
    {
        return substr(hash_hmac('sha256', $data, $this->secret), 0, 20);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function encode(array $args): string
    {
        ksort($args);
        return rtrim(strtr(base64_encode((string) json_encode($args, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $encoded): ?array
    {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $args = $json === false ? null : json_decode($json, true);
        return is_array($args) ? $args : null;
    }
}
