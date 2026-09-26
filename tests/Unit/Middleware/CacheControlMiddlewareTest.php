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
use Qoliber\Trident\Middleware\CacheControlMiddleware;

class CacheControlMiddlewareTest extends TestCase
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
        $this->assertEquals('trident.cacheable', CacheControlMiddleware::ATTRIBUTE_CACHEABLE);
        $this->assertEquals('trident.ttl', CacheControlMiddleware::ATTRIBUTE_TTL);
        $this->assertEquals('trident.stale_while_revalidate', CacheControlMiddleware::ATTRIBUTE_SWR);
        $this->assertEquals('trident.private', CacheControlMiddleware::ATTRIBUTE_PRIVATE);
    }

    public function testProcessWithDefaultSettings(): void
    {
        $middleware = new CacheControlMiddleware();

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(false);

        $this->request->method('getAttribute')
            ->willReturnMap([
                [CacheControlMiddleware::ATTRIBUTE_CACHEABLE, true, true],
                [CacheControlMiddleware::ATTRIBUTE_TTL, 3600, 3600],
                [CacheControlMiddleware::ATTRIBUTE_SWR, 60, 60],
                [CacheControlMiddleware::ATTRIBUTE_PRIVATE, false, false],
            ]);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('Cache-Control', 'public, max-age=3600, stale-while-revalidate=60')
            ->willReturn($this->response);

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithCustomDefaults(): void
    {
        $middleware = new CacheControlMiddleware(7200, 120, true);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(false);

        $this->request->method('getAttribute')
            ->willReturnMap([
                [CacheControlMiddleware::ATTRIBUTE_CACHEABLE, true, true],
                [CacheControlMiddleware::ATTRIBUTE_TTL, 7200, 7200],
                [CacheControlMiddleware::ATTRIBUTE_SWR, 120, 120],
                [CacheControlMiddleware::ATTRIBUTE_PRIVATE, true, true],
            ]);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('Cache-Control', 'private, max-age=7200')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testProcessSkipsWhenCacheControlExists(): void
    {
        $middleware = new CacheControlMiddleware();

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(true);

        $this->response->expects($this->never())
            ->method('withHeader');

        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithCacheDisabled(): void
    {
        $middleware = new CacheControlMiddleware();

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(false);

        $this->request->method('getAttribute')
            ->with(CacheControlMiddleware::ATTRIBUTE_CACHEABLE, true)
            ->willReturn(false);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testProcessWithPrivateResponse(): void
    {
        $middleware = new CacheControlMiddleware();

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(false);

        $this->request->method('getAttribute')
            ->willReturnMap([
                [CacheControlMiddleware::ATTRIBUTE_CACHEABLE, true, true],
                [CacheControlMiddleware::ATTRIBUTE_TTL, 3600, 1800],
                [CacheControlMiddleware::ATTRIBUTE_SWR, 60, 30],
                [CacheControlMiddleware::ATTRIBUTE_PRIVATE, false, true],
            ]);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('Cache-Control', 'private, max-age=1800')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testProcessWithZeroSwr(): void
    {
        $middleware = new CacheControlMiddleware(3600, 0, false);

        $this->handler->method('handle')
            ->willReturn($this->response);

        $this->response->method('hasHeader')
            ->with('Cache-Control')
            ->willReturn(false);

        $this->request->method('getAttribute')
            ->willReturnMap([
                [CacheControlMiddleware::ATTRIBUTE_CACHEABLE, true, true],
                [CacheControlMiddleware::ATTRIBUTE_TTL, 3600, 3600],
                [CacheControlMiddleware::ATTRIBUTE_SWR, 0, 0],
                [CacheControlMiddleware::ATTRIBUTE_PRIVATE, false, false],
            ]);

        $this->response->expects($this->once())
            ->method('withHeader')
            ->with('Cache-Control', 'public, max-age=3600')
            ->willReturn($this->response);

        $middleware->process($this->request, $this->handler);
    }

    public function testDisableCache(): void
    {
        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(CacheControlMiddleware::ATTRIBUTE_CACHEABLE, false)
            ->willReturn($this->request);

        $result = CacheControlMiddleware::disableCache($this->request);

        $this->assertSame($this->request, $result);
    }

    public function testSetTtl(): void
    {
        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(CacheControlMiddleware::ATTRIBUTE_TTL, 7200)
            ->willReturn($this->request);

        $result = CacheControlMiddleware::setTtl($this->request, 7200);

        $this->assertSame($this->request, $result);
    }

    public function testSetPrivate(): void
    {
        $this->request->expects($this->once())
            ->method('withAttribute')
            ->with(CacheControlMiddleware::ATTRIBUTE_PRIVATE, true)
            ->willReturn($this->request);

        $result = CacheControlMiddleware::setPrivate($this->request);

        $this->assertSame($this->request, $result);
    }
}
