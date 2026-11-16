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

use Closure;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qoliber\Trident\Middleware\CacheControlDecision;
use Qoliber\Trident\Middleware\CacheControlMiddleware;

/**
 * Real PSR-7 messages, not mocks. The defect these tests pin was invisible to
 * mocks: a mocked `withAttribute()` returned the same request object, so a
 * helper that only returned a new request looked as if it worked.
 */
class CacheControlMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): ServerRequestInterface
    {
        return new ServerRequest('GET', 'https://shop.test/account', $headers);
    }

    /**
     * A handler that runs `$application` with the request it was given and
     * answers with `$response` (a bare 200 by default).
     *
     * @param (Closure(ServerRequestInterface): void)|null $application
     */
    private function handler(?Closure $application = null, ?ResponseInterface $response = null): RequestHandlerInterface
    {
        return new class ($application, $response ?? new Response(200)) implements RequestHandlerInterface {
            public function __construct(
                private readonly ?Closure $application,
                private readonly ResponseInterface $response
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->application !== null) {
                    ($this->application)($request);
                }

                return $this->response;
            }
        };
    }

    /**
     * @param (Closure(ServerRequestInterface): void)|null $application
     */
    private function cacheControl(
        CacheControlMiddleware $middleware,
        ServerRequestInterface $request,
        ?Closure $application = null
    ): string {
        return $middleware->process($request, $this->handler($application))->getHeaderLine('Cache-Control');
    }

    public function testConstants(): void
    {
        $this->assertEquals('trident.cacheable', CacheControlMiddleware::ATTRIBUTE_CACHEABLE);
        $this->assertEquals('trident.ttl', CacheControlMiddleware::ATTRIBUTE_TTL);
        $this->assertEquals('trident.stale_while_revalidate', CacheControlMiddleware::ATTRIBUTE_SWR);
        $this->assertEquals('trident.private', CacheControlMiddleware::ATTRIBUTE_PRIVATE);
        $this->assertEquals('trident.cache_decision', CacheControlMiddleware::ATTRIBUTE_DECISION);
    }

    public function testProcessWithDefaultSettings(): void
    {
        $this->assertSame(
            'public, max-age=3600, stale-while-revalidate=60',
            $this->cacheControl(new CacheControlMiddleware(), $this->request())
        );
    }

    public function testProcessWithCustomDefaults(): void
    {
        $this->assertSame(
            'private, max-age=7200',
            $this->cacheControl(new CacheControlMiddleware(7200, 120, true), $this->request())
        );
    }

    public function testProcessWithZeroSwr(): void
    {
        $this->assertSame(
            'public, max-age=3600',
            $this->cacheControl(new CacheControlMiddleware(3600, 0, false), $this->request())
        );
    }

    public function testProcessSkipsWhenCacheControlExists(): void
    {
        $response = new Response(200, ['Cache-Control' => 'public, s-maxage=30']);
        $handler = $this->handler(
            static function (ServerRequestInterface $request): void {
                CacheControlMiddleware::disableCache($request);
            },
            $response
        );

        $result = (new CacheControlMiddleware())->process($this->request(['Cookie' => 'sid=1']), $handler);

        $this->assertSame($response, $result);
        $this->assertSame('public, s-maxage=30', $result->getHeaderLine('Cache-Control'));
    }

    // -- The helpers reach the middleware without their return value ---------

    public function testDisableCacheCalledInTheHandlerReachesTheMiddleware(): void
    {
        $this->assertSame(
            'no-store, no-cache, must-revalidate',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::disableCache($request);
                }
            )
        );
    }

    public function testSetPrivateCalledInTheHandlerReachesTheMiddleware(): void
    {
        $this->assertSame(
            'private, max-age=3600',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setPrivate($request);
                }
            )
        );
    }

    public function testSetTtlCalledInTheHandlerReachesTheMiddleware(): void
    {
        $this->assertSame(
            'public, max-age=300, stale-while-revalidate=60',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setTtl($request, 300);
                }
            )
        );
    }

    public function testADecisionMadeOnADerivedRequestStillReachesTheMiddleware(): void
    {
        // An inner layer (a router, another middleware) hands the application
        // a request it derived from the one this middleware passed on.
        $this->assertSame(
            'private, max-age=60',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    $derived = $request->withAttribute('route', 'account')->withHeader('X-Inner', '1');
                    $derived = CacheControlMiddleware::setTtl($derived, 60);
                    CacheControlMiddleware::setPrivate($derived);
                }
            )
        );
    }

    public function testDisableCacheWinsOverPrivateAndTtl(): void
    {
        $this->assertSame(
            'no-store, no-cache, must-revalidate',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setTtl($request, 300);
                    CacheControlMiddleware::setPrivate($request);
                    CacheControlMiddleware::disableCache($request);
                }
            )
        );
    }

    public function testEachRequestGetsItsOwnDecision(): void
    {
        $middleware = new CacheControlMiddleware();

        $first = $this->cacheControl(
            $middleware,
            $this->request(),
            static function (ServerRequestInterface $request): void {
                CacheControlMiddleware::setPrivate($request);
            }
        );
        $second = $this->cacheControl($middleware, $this->request());

        $this->assertSame('private, max-age=3600', $first);
        $this->assertSame('public, max-age=3600, stale-while-revalidate=60', $second);
    }

    // -- Requests that carry credentials -------------------------------------

    public function testACookieBearingRequestIsPrivateWhenTheApplicationDecidedNothing(): void
    {
        $this->assertSame(
            'private, max-age=3600',
            $this->cacheControl(new CacheControlMiddleware(), $this->request(['Cookie' => 'session=alice']))
        );
    }

    public function testAnAuthorizationBearingRequestIsPrivateWhenTheApplicationDecidedNothing(): void
    {
        $this->assertSame(
            'private, max-age=3600',
            $this->cacheControl(new CacheControlMiddleware(), $this->request(['Authorization' => 'Bearer abc']))
        );
    }

    public function testParsedCookiesCountEvenWithoutTheHeader(): void
    {
        $request = $this->request()->withCookieParams(['session' => 'alice']);

        $this->assertSame('private, max-age=3600', $this->cacheControl(new CacheControlMiddleware(), $request));
    }

    public function testAnEmptyCookieHeaderIsNotACredential(): void
    {
        $this->assertSame(
            'public, max-age=3600, stale-while-revalidate=60',
            $this->cacheControl(new CacheControlMiddleware(), $this->request(['Cookie' => '']))
        );
    }

    public function testSetPublicSharesTheResponseOfACookieBearingRequest(): void
    {
        $this->assertSame(
            'public, max-age=3600, stale-while-revalidate=60',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(['Cookie' => '_ga=GA1.2.3']),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setPublic($request);
                }
            )
        );
    }

    public function testSetPublicOverridesAPrivateDefault(): void
    {
        $this->assertSame(
            'public, max-age=3600, stale-while-revalidate=60',
            $this->cacheControl(
                new CacheControlMiddleware(3600, 60, true),
                $this->request(),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setPublic($request);
                }
            )
        );
    }

    public function testATtlAloneDoesNotMakeACookieBearingResponsePublic(): void
    {
        $this->assertSame(
            'private, max-age=300',
            $this->cacheControl(
                new CacheControlMiddleware(),
                $this->request(['Cookie' => 'session=alice']),
                static function (ServerRequestInterface $request): void {
                    CacheControlMiddleware::setTtl($request, 300);
                }
            )
        );
    }

    // -- Attributes set before the middleware ran ----------------------------

    public function testAttributesSetUpstreamAreHonoured(): void
    {
        $request = $this->request()
            ->withAttribute(CacheControlMiddleware::ATTRIBUTE_TTL, 1800)
            ->withAttribute(CacheControlMiddleware::ATTRIBUTE_SWR, 30);

        $this->assertSame(
            'public, max-age=1800, stale-while-revalidate=30',
            $this->cacheControl(new CacheControlMiddleware(), $request)
        );
    }

    public function testCacheDisabledUpstreamIsHonoured(): void
    {
        $request = $this->request()->withAttribute(CacheControlMiddleware::ATTRIBUTE_CACHEABLE, false);

        $this->assertSame(
            'no-store, no-cache, must-revalidate',
            $this->cacheControl(new CacheControlMiddleware(), $request)
        );
    }

    public function testPrivateSetUpstreamIsHonoured(): void
    {
        $request = CacheControlMiddleware::setPrivate($this->request());

        $this->assertSame('private, max-age=3600', $this->cacheControl(new CacheControlMiddleware(), $request));
    }

    public function testPublicSetUpstreamIsAnExplicitDecisionForACookieBearingRequest(): void
    {
        $request = CacheControlMiddleware::setPublic($this->request(['Cookie' => '_ga=GA1.2.3']));

        $this->assertSame(
            'public, max-age=3600, stale-while-revalidate=60',
            $this->cacheControl(new CacheControlMiddleware(), $request)
        );
    }

    // -- The helpers on their own --------------------------------------------

    public function testHelpersStillReturnARequestCarryingTheAttribute(): void
    {
        $request = $this->request();

        $this->assertFalse(
            CacheControlMiddleware::disableCache($request)->getAttribute(CacheControlMiddleware::ATTRIBUTE_CACHEABLE)
        );
        $this->assertSame(
            7200,
            CacheControlMiddleware::setTtl($request, 7200)->getAttribute(CacheControlMiddleware::ATTRIBUTE_TTL)
        );
        $this->assertTrue(
            CacheControlMiddleware::setPrivate($request)->getAttribute(CacheControlMiddleware::ATTRIBUTE_PRIVATE)
        );
        $this->assertFalse(
            CacheControlMiddleware::setPublic($request)->getAttribute(CacheControlMiddleware::ATTRIBUTE_PRIVATE)
        );
        // Outside the middleware there is no decision to write to.
        $this->assertNull(CacheControlMiddleware::decision($request));
    }

    public function testTheHandlerSeesTheDecisionTheMiddlewareReads(): void
    {
        $seen = null;
        $this->cacheControl(
            new CacheControlMiddleware(),
            $this->request(),
            static function (ServerRequestInterface $request) use (&$seen): void {
                $seen = CacheControlMiddleware::decision($request);
                CacheControlMiddleware::setTtl($request, 42);
            }
        );

        $this->assertInstanceOf(CacheControlDecision::class, $seen);
        $this->assertSame(42, $seen->ttl);
        $this->assertNull($seen->private);
        $this->assertNull($seen->cacheable);
    }
}
