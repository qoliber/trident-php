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
use Qoliber\Trident\Cache\TagCollection;

class CacheTagMiddleware implements MiddlewareInterface
{
    public const HEADER_NAME = 'X-Cache-Tags';
    public const ATTRIBUTE_NAME = 'trident.cache_tags';

    private string $headerName;
    private string $separator;
    private TagCollection $defaultTags;

    public function __construct(
        string $headerName = self::HEADER_NAME,
        string $separator = ',',
        ?TagCollection $defaultTags = null
    ) {
        $this->headerName = $headerName;
        $this->separator = $separator;
        $this->defaultTags = $defaultTags ?? new TagCollection();
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Initialize tag collection in request attributes
        $tags = clone $this->defaultTags;
        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $tags);

        // Process the request
        $response = $handler->handle($request);

        // Get tags from request attribute (may have been modified by handlers)
        /** @var TagCollection $tags */
        $tags = $request->getAttribute(self::ATTRIBUTE_NAME, $this->defaultTags);

        // Merge any existing tags from the response
        if ($response->hasHeader($this->headerName)) {
            $existingTags = TagCollection::fromHeader(
                $response->getHeaderLine($this->headerName),
                $this->separator
            );
            $tags->merge($existingTags);
        }

        // Add cache tags header if we have any
        if (!$tags->isEmpty()) {
            $response = $response->withHeader($this->headerName, $tags->toHeader());
        }

        return $response;
    }

    /**
     * Helper to get the tag collection from a request
     */
    public static function getTags(ServerRequestInterface $request): TagCollection
    {
        /** @var TagCollection $tags */
        $tags = $request->getAttribute(self::ATTRIBUTE_NAME);

        return $tags ?? new TagCollection();
    }

    /**
     * Helper to add tags to a request's collection
     */
    public static function addTags(ServerRequestInterface $request, string ...$tags): void
    {
        $collection = self::getTags($request);
        foreach ($tags as $tag) {
            $collection->add($tag);
        }
    }
}
