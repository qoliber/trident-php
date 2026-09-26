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
 */
class CacheControlMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE_CACHEABLE = 'trident.cacheable';
    public const ATTRIBUTE_TTL = 'trident.ttl';
    public const ATTRIBUTE_SWR = 'trident.stale_while_revalidate';
    public const ATTRIBUTE_PRIVATE = 'trident.private';

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
        $response = $handler->handle($request);

        // Skip if response already has Cache-Control
        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }

        // Check if caching is explicitly disabled
        $cacheable = $request->getAttribute(self::ATTRIBUTE_CACHEABLE, true);
        if (!$cacheable) {
            return $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        // Get cache settings from request attributes
        $ttl = $request->getAttribute(self::ATTRIBUTE_TTL, $this->defaultTtl);
        $swr = $request->getAttribute(self::ATTRIBUTE_SWR, $this->defaultSwr);
        $private = $request->getAttribute(self::ATTRIBUTE_PRIVATE, $this->defaultPrivate);

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
     */
    public static function disableCache(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withAttribute(self::ATTRIBUTE_CACHEABLE, false);
    }

    /**
     * Set custom TTL for the request
     */
    public static function setTtl(ServerRequestInterface $request, int $ttl): ServerRequestInterface
    {
        return $request->withAttribute(self::ATTRIBUTE_TTL, $ttl);
    }

    /**
     * Mark response as private (user-specific content)
     */
    public static function setPrivate(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withAttribute(self::ATTRIBUTE_PRIVATE, true);
    }
}
