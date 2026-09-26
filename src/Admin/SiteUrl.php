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
 * A storefront URL split the way the admin API addresses a cache entry: path
 * (with query), host and scheme as separate fields.
 *
 * The engine's cache key is `METHOD:scheme:host:path`. Endpoints that take an
 * absolute URL in `url` do not parse it (explain keys it under
 * `_invalid_host_`, coverage never finds it), and the ones that take a `host`
 * default the scheme to https — so a page on an http storefront reads as "not
 * cached" unless the scheme is sent too. One parser, used by every call that
 * names a page.
 */
final class SiteUrl
{
    private function __construct(
        public readonly string $path,
        public readonly ?string $host,
        public readonly ?string $scheme
    ) {
    }

    /**
     * An absolute URL (`https://shop.example/a?b=1`), or a path (`/a`), which
     * then needs `$defaultHost` / `$defaultScheme` to name an entry.
     */
    public static function parse(string $url, ?string $defaultHost = null, ?string $defaultScheme = null): self
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \InvalidArgumentException(sprintf('Not a URL: %s', $url));
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : $defaultScheme;
        $host = $defaultHost;
        if (isset($parts['host'])) {
            // The key a browser's request lands under: an ASCII (punycode) host,
            // and no port when it is the scheme's default.
            $host = self::asciiHost(strtolower($parts['host']));
            $port = $parts['port'] ?? null;
            $default = ['http' => 80, 'https' => 443][$scheme ?? ''] ?? null;
            if ($port !== null && $port !== $default) {
                $host .= ':' . $port;
            }
        }
        if ($scheme !== null && !in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException(sprintf('Not an http(s) URL: %s', $url));
        }
        $path = $parts['path'] ?? '/';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }
        return new self($path, $host, $scheme);
    }

    /**
     * IDN host to ASCII (punycode). Needs ext-intl; without it a non-ASCII host
     * is kept as written and will not match the browser's key (documented).
     */
    private static function asciiHost(string $host): string
    {
        if (preg_match('/[^\x20-\x7e]/', $host) !== 1 || !function_exists('idn_to_ascii')) {
            return $host;
        }
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return is_string($ascii) && $ascii !== '' ? $ascii : $host;
    }

    /**
     * The page as an absolute URL; the path alone when host or scheme is unknown.
     */
    public function absolute(): string
    {
        if ($this->host === null || $this->scheme === null) {
            return $this->path;
        }
        return $this->scheme . '://' . $this->host . $this->path;
    }

    /**
     * A page from the engine's cache key, `METHOD:scheme:host[:port]:/path`;
     * null when the key is not in that form.
     *
     * The host may carry a port, so the path starts at the first `:/` after
     * the scheme, not at the third colon.
     *
     * @return array{method: string, page: self}|null
     */
    public static function fromKey(string $key): ?array
    {
        $parts = explode(':', $key, 3);
        if (count($parts) !== 3 || !in_array(strtolower($parts[1]), ['http', 'https'], true)) {
            return null;
        }
        $at = strpos($parts[2], ':/');
        if ($at === false || $at === 0) {
            return null;
        }
        return [
            'method' => $parts[0],
            'page' => new self(substr($parts[2], $at + 1), substr($parts[2], 0, $at), strtolower($parts[1])),
        ];
    }

    /**
     * Request-body fields naming this page: `url` plus `host` / `scheme` when known.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return array_filter(
            ['url' => $this->path, 'host' => $this->host, 'scheme' => $this->scheme],
            static fn (?string $v): bool => $v !== null
        );
    }
}
