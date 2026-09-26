<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Cache\Policy;
use Qoliber\Trident\Cache\RequestContext;

/**
 * The generic rules, with a platform's lists passed in as configuration.
 */
final class PolicyTest extends TestCase
{
    private static function policy(): Policy
    {
        return new Policy(3600, 86400, ['add-to-cart', '_nonce'], ['user_logged_in_'], ['cart_session_', 'items_in_cart']);
    }

    public function testAnonymousPageIsPublicWithSMaxage(): void
    {
        $d = (new Policy(1800, 600))->decide(new RequestContext());
        self::assertTrue($d->cacheable);
        self::assertSame('public, max-age=0, s-maxage=1800, stale-while-revalidate=600', $d->cacheControl);
        self::assertSame('public, max-age=0, s-maxage=60', (new Policy(60, 0))->decide(new RequestContext())->cacheControl);
        self::assertTrue((new Policy())->decide(new RequestContext(method: 'HEAD'))->cacheable);
    }

    /**
     * @return array<string, array{RequestContext, string}>
     */
    public static function neverStored(): array
    {
        return [
            'POST' => [new RequestContext(method: 'POST'), 'method POST'],
            'not a page' => [new RequestContext(isPage: false), 'not a page'],
            'logged in' => [new RequestContext(isLoggedIn: true), 'logged-in'],
            'logged-in cookie even if the platform did not accept it' => [new RequestContext(cookieNames: ['user_logged_in_5c0f']), 'logged-in'],
            'private page' => [new RequestContext(isPrivatePage: true), 'private page'],
            'preview' => [new RequestContext(isPreview: true), 'preview'],
            'state-changing query' => [new RequestContext(query: ['add-to-cart' => '35']), 'query add-to-cart'],
            'do-not-cache' => [new RequestContext(doNotCache: true), 'do-not-cache'],
            'password protected' => [new RequestContext(passwordProtected: true), 'password protected'],
            '404' => [new RequestContext(status: 404), 'status 404'],
            'redirect' => [new RequestContext(status: 301), 'status 301'],
            'sets a cookie' => [new RequestContext(setsCookie: true), 'set-cookie'],
            'app said private' => [new RequestContext(cacheControl: 'private, max-age=60'), 'application said private, max-age=60'],
            'app said no-cache' => [new RequestContext(cacheControl: 'no-cache, no-store'), 'application said no-cache, no-store'],
            'session cookie' => [new RequestContext(cookieNames: ['cart_session_abc']), 'session'],
            'session cookie, exact name' => [new RequestContext(cookieNames: ['items_in_cart']), 'session'],
        ];
    }

    #[DataProvider('neverStored')]
    public function testNeverStored(RequestContext $ctx, string $reason): void
    {
        $d = self::policy()->decide($ctx);
        self::assertFalse($d->cacheable);
        self::assertSame($reason, $d->reason);
        self::assertStringContainsString('no-store', $d->cacheControl);
        self::assertStringContainsString('private', $d->cacheControl);
    }

    public function testUnrelatedCookiesAndQueryDoNotPreventCaching(): void
    {
        self::assertTrue(self::policy()->decide(new RequestContext(cookieNames: ['_ga', 'consent']))->cacheable);
        self::assertTrue(self::policy()->decide(new RequestContext(query: ['orderby' => 'price']))->cacheable);
    }

    public function testWithoutListsOnlyTheUniversalRulesApply(): void
    {
        self::assertTrue((new Policy())->decide(new RequestContext(cookieNames: ['cart_session_abc'], query: ['add-to-cart' => '1']))->cacheable);
    }

    public function testLoggedInWinsOverEverything(): void
    {
        self::assertSame('logged-in', self::policy()->decide(new RequestContext(isLoggedIn: true, isPrivatePage: true, setsCookie: true))->reason);
    }
}
