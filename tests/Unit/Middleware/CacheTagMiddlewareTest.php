<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qoliber\Trident\Cache\TagCollection;
use Qoliber\Trident\Middleware\CacheTagMiddleware;

class CacheTagMiddlewareTest extends TestCase
{
    private ServerRequestInterface&MockObject $request;
    private ResponseInterface&MockObject $response;
    private RequestHandlerInterface&MockObject $handler;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    public function testConstants(): void
    {
        $this->assertEquals('X-Cache-Tags', CacheTagMiddleware::HEADER_NAME);
        $this->assertEquals('trident.cache_tags', CacheTagMiddleware::ATTRIBUTE_NAME);
    }

    public function testProcessAddsTagCollectionToRequest(): void
    {
        $middleware = new CacheTagMiddleware();

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(
                CacheTagMiddleware::ATTRIBUTE_NAME,
                $this->isInstanceOf(TagCollection::class)
            )
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->willReturn(new TagCollection());

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('X-Cache-Tags')
            ->willReturn(false);

        $middleware->process($this->request, $this->handler);
    }

    public function testProcessAddsHeaderWhenTagsExist(): void
    {
        $middleware = new CacheTagMiddleware();
        $tags = TagCollection::create()->add('product.1')->add('category.2');

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->with(CacheTagMiddleware::ATTRIBUTE_NAME, $this->anything())
            ->willReturn($tags);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('X-Cache-Tags')
            ->willReturn(false);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('X-Cache-Tags', 'product.1,category.2')
            ->willReturn($this->response);

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessMergesExistingHeaderTags(): void
    {
        $middleware = new CacheTagMiddleware();
        $requestTags = TagCollection::create()->add('product.1');

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->willReturn($requestTags);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('X-Cache-Tags')
            ->willReturn(true);

        $this->response->method('getHeaderLine')
            ->with('X-Cache-Tags')
            ->willReturn('category.2,store.default');

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('X-Cache-Tags', $this->stringContains('product.1'))
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testProcessSkipsHeaderWhenTagsEmpty(): void
    {
        $middleware = new CacheTagMiddleware();
        $emptyTags = new TagCollection();

        $this->request->method('withAttribute')
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->willReturn($emptyTags);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->willReturn(false);

        $this->response->expects($this->never())
            ->method('withHeader');

        $middleware->process($this->request, $this->handler);
    }

    public function testCustomHeaderName(): void
    {
        $middleware = new CacheTagMiddleware('X-Custom-Tags');
        $tags = TagCollection::create()->add('tag1');

        $this->request->method('withAttribute')
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->willReturn($tags);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('X-Custom-Tags')
            ->willReturn(false);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('X-Custom-Tags', 'tag1')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testDefaultTags(): void
    {
        $defaultTags = TagCollection::create()->add('global.cache');
        $middleware = new CacheTagMiddleware(
            CacheTagMiddleware::HEADER_NAME,
            ',',
            $defaultTags
        );

        $capturedTags = null;

        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(
                CacheTagMiddleware::ATTRIBUTE_NAME,
                $this->callback(function (TagCollection $tags) use (&$capturedTags) {
                    $capturedTags = $tags;
                    return $tags->has('global.cache');
                })
            )
            ->willReturn($this->request);

        $this->request->method('getAttribute')
            ->willReturn($capturedTags ?? $defaultTags);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->willReturn(false);

        $this->response->method('withHeader')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testGetTags(): void
    {
        $tags = TagCollection::create()->add('product.1');

        $this->request->method('getAttribute')
            ->with(CacheTagMiddleware::ATTRIBUTE_NAME)
            ->willReturn($tags);

        $result = CacheTagMiddleware::getTags($this->request);

        $this->assertSame($tags, $result);
    }

    public function testGetTagsReturnsEmptyCollectionWhenNoAttribute(): void
    {
        $this->request->method('getAttribute')
            ->with(CacheTagMiddleware::ATTRIBUTE_NAME)
            ->willReturn(null);

        $result = CacheTagMiddleware::getTags($this->request);

        $this->assertInstanceOf(TagCollection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testAddTags(): void
    {
        $tags = TagCollection::create();

        $this->request->method('getAttribute')
            ->with(CacheTagMiddleware::ATTRIBUTE_NAME)
            ->willReturn($tags);

        CacheTagMiddleware::addTags($this->request, 'product.1', 'category.2');

        $this->assertTrue($tags->has('product.1'));
        $this->assertTrue($tags->has('category.2'));
    }
}
