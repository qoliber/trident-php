<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Admin\Payload;
use Qoliber\Trident\Admin\SiteUrl;
use Qoliber\Trident\Events\EventStream;

final class SiteUrlAndPayloadTest extends TestCase
{
    /**
     * A browser never sends the scheme's default port in Host: the key is
     * `shop.example`, not `shop.example:443`.
     */
    public function testTheSchemesDefaultPortIsDropped(): void
    {
        self::assertSame('shop.example', SiteUrl::parse('https://shop.example:443/a')->host);
        self::assertSame('shop.example', SiteUrl::parse('http://shop.example:80/a')->host);
        self::assertSame('shop.example:8443', SiteUrl::parse('https://shop.example:8443/a')->host);
        self::assertSame('shop.example:443', SiteUrl::parse('http://shop.example:443/a')->host, 'not the default for http');
    }

    public function testAnIdnHostIsPunycode(): void
    {
        if (!function_exists('idn_to_ascii')) {
            self::markTestSkipped('ext-intl not loaded: IDN hosts are kept as written (documented limit)');
        }
        self::assertSame('xn--weki-pqa88b6m.pl', SiteUrl::parse('https://żółweki.pl/x')->host);
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function urls(): array
    {
        return [
            'absolute http with port' => ['http://Shop.Example:8480/shop/?orderby=price', ['url' => '/shop/?orderby=price', 'host' => 'shop.example:8480', 'scheme' => 'http']],
            'absolute https'          => ['https://shop.example/p/beanie/', ['url' => '/p/beanie/', 'host' => 'shop.example', 'scheme' => 'https']],
            'bare host'               => ['https://shop.example', ['url' => '/', 'host' => 'shop.example', 'scheme' => 'https']],
            'path only'               => ['/cart/', ['url' => '/cart/']],
            'path without slash'      => ['shop', ['url' => '/shop']],
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('urls')]
    public function testSplitsAUrlTheWayTheEngineKeysIt(string $url, array $fields): void
    {
        self::assertSame($fields, SiteUrl::parse($url)->fields());
    }

    public function testAPathTakesTheDefaults(): void
    {
        self::assertSame(
            ['url' => '/shop/', 'host' => 'localhost:8480', 'scheme' => 'http'],
            SiteUrl::parse('/shop/', 'localhost:8480', 'http')->fields()
        );
    }

    public function testAbsoluteAndFromKey(): void
    {
        self::assertSame('http://localhost:8480/shop/?a=1', SiteUrl::parse('/shop/?a=1', 'localhost:8480', 'http')->absolute());
        self::assertSame('/shop/', SiteUrl::parse('/shop/')->absolute(), 'no host: the path');
        $key = SiteUrl::fromKey('GET:http:localhost:8480:/p/a:b/');
        self::assertNotNull($key);
        self::assertSame('GET', $key['method']);
        self::assertSame('http://localhost:8480/p/a:b/', $key['page']->absolute());
        self::assertSame('shop.example', SiteUrl::fromKey('HEAD:https:shop.example:/')['page']->host ?? null);
        self::assertNull(SiteUrl::fromKey('garbage'));
        self::assertNull(SiteUrl::fromKey('GET:https:shop.example'));
        self::assertNull(SiteUrl::fromKey('GET:ftp:x:/y'));
    }

    public function testRefusesANonHttpScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SiteUrl::parse('file:///etc/passwd');
    }

    public function testPayloadAccessorsNeverThrow(): void
    {
        $p = new Payload(['state' => 'idle', 'last_run' => ['completed' => '7', 'cancelled' => false], 'urls' => [['url' => '/a'], 'junk']]);
        self::assertSame('idle', $p->string('state'));
        self::assertSame(7, $p->int('last_run.completed'));
        self::assertFalse($p->bool('last_run.cancelled', true));
        self::assertSame(0, $p->int('missing.deep'));
        self::assertSame('x', $p->string('last_run', 'x'), 'an array is not a string');
        self::assertSame([['url' => '/a']], $p->rows('urls'));
        self::assertSame([], $p->rows('state'));
        self::assertTrue($p->has('last_run.cancelled'));
        self::assertFalse($p->has('last_run.nope'));
    }

    public function testParsesABufferedSseBurst(): void
    {
        $chunk = "event: connected\ndata: {\"message\":\"Connected\"}\n\n: keepalive\n\n"
            . "event: request\ndata: {\"path\":\"/shop/\",\"cache_status\":\"HIT\"}\n\n"
            . "event: request\ndata: {\"path\":\"/half";
        $events = EventStream::parseChunk($chunk);

        self::assertCount(2, $events, 'keepalive has no data; the trailing half event is dropped');
        self::assertSame('connected', $events[0]->getType());
        self::assertSame('/shop/', $events[1]->get('path'));
        self::assertSame('HIT', $events[1]->get('cache_status'));
    }

    public function testParsesCrlfSse(): void
    {
        $events = EventStream::parseChunk("event: request\r\ndata: {\"path\":\"/x\"}\r\n\r\n");
        self::assertSame('/x', $events[0]->get('path'));
    }
}
