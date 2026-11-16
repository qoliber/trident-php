<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Middleware;

/**
 * What the application decided about caching one response.
 *
 * Mutable on purpose. A PSR-7 request is immutable, so a handler that calls
 * `$request->withAttribute(...)` gets a new request that the middleware which
 * called it never sees. CacheControlMiddleware therefore puts ONE instance of
 * this class into the request before calling the handler and reads the same
 * instance afterwards; whatever the handler changed on it is still there.
 *
 * `null` means "the application did not decide", which is not the same as
 * `false`: an undecided `private` on a request that carries credentials is
 * treated as private, a decided `false` is not.
 */
final class CacheControlDecision
{
    /** `false`: the response must not be stored at all. */
    public ?bool $cacheable = null;

    /** Freshness lifetime in seconds. */
    public ?int $ttl = null;

    /** `stale-while-revalidate` in seconds; only sent on a public response. */
    public ?int $swr = null;

    /** `true`: per-user content. `false`: explicitly shareable. */
    public ?bool $private = null;
}
