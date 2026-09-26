<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Cache;

/**
 * Generic full-page cacheability rules: method, page type, cookies, query
 * parameters, status, Set-Cookie. The platform-specific lists (which query
 * parameters change state, which cookies mean "logged in" or "has a cart") are
 * configuration the platform passes in.
 *
 * Every rule errs towards "do not store": a page stored for the wrong visitor
 * is a data leak, a page not stored is only a slower response.
 *
 * Session cookies are the one rule that is not a bypass. A visitor with a
 * session (a cart) is still SERVED cached pages — they are rendered for an
 * anonymous visitor and identical for everyone, with the personal parts filled
 * in client-side — but a page rendered FOR that visitor has their session in
 * it, so it is sent `private, no-store` and only anonymous renders fill the
 * cache. The usual "bypass once a cart exists" switches the cache off for the
 * visitors who are about to buy.
 */
final class Policy
{
    /**
     * @param int          $ttl                   s-maxage for cacheable pages.
     * @param int          $swr                   stale-while-revalidate (0: none).
     * @param list<string> $bypassQueryParams     Parameters that change state or are one-off.
     * @param list<string> $loggedInCookies       Cookie name PREFIXES that mean "logged in".
     * @param list<string> $sessionCookies        Cookie name PREFIXES that mean "has a session".
     */
    public function __construct(
        private readonly int $ttl = 3600,
        private readonly int $swr = 86400,
        private readonly array $bypassQueryParams = [],
        private readonly array $loggedInCookies = [],
        private readonly array $sessionCookies = []
    ) {
    }

    public function decide(RequestContext $ctx): Decision
    {
        $method = strtoupper($ctx->method);
        if ($method !== 'GET' && $method !== 'HEAD') {
            return Decision::privateNoStore('method ' . $method);
        }
        if (!$ctx->isPage) {
            return Decision::privateNoStore('not a page');
        }
        if ($ctx->isLoggedIn || self::hasCookie($ctx->cookieNames, $this->loggedInCookies)) {
            return Decision::privateNoStore('logged-in');
        }
        if ($ctx->isPrivatePage) {
            return Decision::privateNoStore('private page');
        }
        if ($ctx->isPreview) {
            return Decision::privateNoStore('preview');
        }
        foreach ($this->bypassQueryParams as $param) {
            if (array_key_exists($param, $ctx->query)) {
                return Decision::privateNoStore('query ' . $param);
            }
        }
        if ($ctx->doNotCache) {
            return Decision::privateNoStore('do-not-cache');
        }
        if ($ctx->passwordProtected) {
            return Decision::privateNoStore('password protected');
        }
        if ($ctx->status !== 200) {
            return Decision::privateNoStore('status ' . $ctx->status);
        }
        if ($ctx->setsCookie) {
            // A stored Set-Cookie would hand this visitor's session to the next one.
            return Decision::privateNoStore('set-cookie');
        }
        $app = strtolower($ctx->cacheControl);
        if ($app !== '' && preg_match('/\b(no-store|private|no-cache)\b/', $app) === 1) {
            return Decision::privateNoStore('application said ' . trim($ctx->cacheControl));
        }
        if (self::hasCookie($ctx->cookieNames, $this->sessionCookies)) {
            // Served from cache when stored; never stored from here.
            return Decision::privateNoStore('session');
        }
        return Decision::cacheable($this->ttl, $this->swr);
    }

    /**
     * @param list<string> $names    Request cookie names.
     * @param list<string> $prefixes Prefixes (a full name is its own prefix).
     */
    public static function hasCookie(array $names, array $prefixes): bool
    {
        foreach ($names as $name) {
            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with((string) $name, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }
}
