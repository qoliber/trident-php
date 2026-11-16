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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to add Cache-Control headers for Trident
 *
 * The response gets a `Cache-Control` only when the application sent none.
 *
 * The application states its decision with the static helpers, from anywhere
 * that holds the request the handler received:
 *
 *     CacheControlMiddleware::setPrivate($request);
 *
 * The helpers write to a {@see CacheControlDecision} that this middleware
 * placed in the request before calling the handler, so the decision reaches
 * the middleware even though the request itself is immutable. (They used to
 * return a new request and nothing else, which the middleware never saw: every
 * response without `Cache-Control` went out `public`.)
 *
 * A request that carries a `Cookie` or `Authorization` header is answered
 * `private` unless the application said otherwise with {@see setPublic()}. A
 * shared cache keys on the URL, not on who asked.
 */
class CacheControlMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE_CACHEABLE = 'trident.cacheable';
    public const ATTRIBUTE_TTL = 'trident.ttl';
    public const ATTRIBUTE_SWR = 'trident.stale_while_revalidate';
    public const ATTRIBUTE_PRIVATE = 'trident.private';

    /** Holds the {@see CacheControlDecision} for the request being handled. */
    public const ATTRIBUTE_DECISION = 'trident.cache_decision';

    private int $defaultTtl;
    private int $defaultSwr;
    private bool $defaultPrivate;

    public function __construct(
        int $defaultTtl = 3600,
        int $defaultSwr = 60,
        bool $defaultPrivate = false
    ) {
        $this->defaultTtl = $defaultTtl;
        $this->defaultSwr = $defaultSwr;
        $this->defaultPrivate = $defaultPrivate;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // One mutable decision per request, visible to everything downstream
        // and still in hand here once the handler returns.
        $decision = self::decisionFromAttributes($request);
        $request = $request->withAttribute(self::ATTRIBUTE_DECISION, $decision);

        $response = $handler->handle($request);

        // Skip if response already has Cache-Control
        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        // Check if caching is explicitly disabled
        if ($decision->cacheable === false) {
            return $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $ttl = $decision->ttl ?? $this->defaultTtl;
        $swr = $decision->swr ?? $this->defaultSwr;
        // Undecided: the configured default, and never public for a request
        // that identified its sender.
        $private = $decision->private
            ?? ($this->defaultPrivate || self::carriesCredentials($request));

        // Build Cache-Control header
        $directives = [];

        $directives[] = $private ? 'private' : 'public';
        $directives[] = "max-age={$ttl}";

        if ($swr > 0 && !$private) {
            $directives[] = "stale-while-revalidate={$swr}";
        }

        $cacheControl = implode(', ', $directives);

        return $response->withHeader('Cache-Control', $cacheControl);
    }

    /**
     * Mark a request as non-cacheable
     *
     * The returned request also carries the legacy attribute; using the return
     * value is not required.
     */
    public static function disableCache(ServerRequestInterface $request): ServerRequestInterface
    {
        $decision = self::decision($request);
        if ($decision !== null) {
            $decision->cacheable = false;
        }

        return $request->withAttribute(self::ATTRIBUTE_CACHEABLE, false);
    }

    /**
     * Set custom TTL for the request
     *
     * A TTL says how long, not for whom: it does not make the response of a
     * request with credentials public. Use {@see setPublic()} for that.
     */
    public static function setTtl(ServerRequestInterface $request, int $ttl): ServerRequestInterface
    {
        $decision = self::decision($request);
        if ($decision !== null) {
            $decision->ttl = $ttl;
        }

        return $request->withAttribute(self::ATTRIBUTE_TTL, $ttl);
    }

    /**
     * Mark response as private (user-specific content)
     */
    public static function setPrivate(ServerRequestInterface $request): ServerRequestInterface
    {
        $decision = self::decision($request);
        if ($decision !== null) {
            $decision->private = true;
        }

        return $request->withAttribute(self::ATTRIBUTE_PRIVATE, true);
    }

    /**
     * Mark response as shareable even though the request carried a `Cookie` or
     * `Authorization` header.
     *
     * Only for a response that is the same for every visitor: Trident will
     * serve it to people who sent other credentials, or none.
     */
    public static function setPublic(ServerRequestInterface $request): ServerRequestInterface
    {
        $decision = self::decision($request);
        if ($decision !== null) {
            $decision->private = false;
        }

        return $request->withAttribute(self::ATTRIBUTE_PRIVATE, false);
    }

    /**
     * The decision this middleware placed in the request, or null when the
     * request did not pass through it (yet).
     */
    public static function decision(ServerRequestInterface $request): ?CacheControlDecision
    {
        $decision = $request->getAttribute(self::ATTRIBUTE_DECISION);

        return $decision instanceof CacheControlDecision ? $decision : null;
    }

    /**
     * Start from the attributes set before this middleware ran: those are on
     * the request it receives, so they have always been honoured.
     */
    private static function decisionFromAttributes(ServerRequestInterface $request): CacheControlDecision
    {
        $decision = new CacheControlDecision();

        $cacheable = $request->getAttribute(self::ATTRIBUTE_CACHEABLE);
        if ($cacheable !== null) {
            $decision->cacheable = (bool) $cacheable;
        }

        $ttl = $request->getAttribute(self::ATTRIBUTE_TTL);
        if (is_numeric($ttl)) {
            $decision->ttl = (int) $ttl;
        }

        $swr = $request->getAttribute(self::ATTRIBUTE_SWR);
        if (is_numeric($swr)) {
            $decision->swr = (int) $swr;
        }

        $private = $request->getAttribute(self::ATTRIBUTE_PRIVATE);
        if ($private !== null) {
            $decision->private = (bool) $private;
        }

        return $decision;
    }

    /**
     * Whether the request says who is asking.
     */
    private static function carriesCredentials(ServerRequestInterface $request): bool
    {
        return trim($request->getHeaderLine('Authorization')) !== ''
            || trim($request->getHeaderLine('Cookie')) !== ''
            || $request->getCookieParams() !== [];
    }
}
