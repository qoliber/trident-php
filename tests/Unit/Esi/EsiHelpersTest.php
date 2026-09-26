<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Esi;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Esi\FragmentResponse;
use Qoliber\Trident\Esi\FragmentUrl;
use Qoliber\Trident\Esi\Markup;
use Qoliber\Trident\Esi\TridentOrigin;
use Qoliber\Trident\Tags\TagSet;

final class EsiHelpersTest extends TestCase
{
    public function testIncludeWithFallback(): void
    {
        $html = Markup::includeWithFallback('/?trident-esi=menu&a=x&s=y', '<nav>menu</nav>');
        self::assertSame('<!--esi <esi:include src="/?trident-esi=menu&a=x&s=y"/> --><esi:remove><nav>menu</nav></esi:remove>', $html);
        self::assertTrue(Markup::containsRawEsi($html));
        self::assertFalse(Markup::containsRawEsi('<nav>menu</nav>'));
    }

    public function testUnsafeSrcIsRefused(): void
    {
        foreach (['https://evil/x', '/x"><script>', '/x --> <b>', 'x', "/x\n"] as $bad) {
            try {
                Markup::includeWithFallback($bad, '');
                self::fail('accepted ' . $bad);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSignedUrlRoundTripAndTamperDetection(): void
    {
        $urls = new FragmentUrl('secret');
        $src = $urls->build('/', 'trident-esi', 'menu', ['location' => 'primary', 'depth' => 0]);
        Markup::assertSafeSrc($src);
        parse_str((string) parse_url($src, PHP_URL_QUERY), $q);
        self::assertSame('menu', $q['trident-esi']);
        self::assertSame(['depth' => 0, 'location' => 'primary'], $urls->verify('menu', $q['a'], $q['s']));
        self::assertNull($urls->verify('widgets', $q['a'], $q['s']), 'signature binds the type');
        self::assertNull($urls->verify('menu', FragmentUrl::encode(['location' => 'x']), $q['s']));
        self::assertNull((new FragmentUrl('other'))->verify('menu', $q['a'], $q['s']));
        self::assertNull(FragmentUrl::decode('!!!'));
    }

    public function testSameArgumentsGiveTheSameUrl(): void
    {
        $urls = new FragmentUrl('k');
        self::assertSame($urls->build('/', 'e', 't', ['b' => 1, 'a' => 2]), $urls->build('/', 'e', 't', ['a' => 2, 'b' => 1]));
    }

    public function testFragmentHeaders(): void
    {
        $tags = new TagSet('shop1_');
        $tags->addAll(['all', 'menu']);
        self::assertSame(
            ['Cache-Control' => 'public, max-age=0, s-maxage=600', 'X-Cache-Tags' => 'shop1_all,shop1_menu', 'X-Robots-Tag' => 'noindex'],
            FragmentResponse::shared(600, $tags)
        );
        self::assertStringContainsString('no-store', FragmentResponse::private()['Cache-Control']);
    }

    public function testTridentOrigin(): void
    {
        self::assertTrue(TridentOrigin::matches('127.0.0.1', ['127.0.0.1']));
        self::assertTrue(TridentOrigin::matches('172.21.0.5', ['10.0.0.0/8', '172.16.0.0/12']));
        self::assertFalse(TridentOrigin::matches('172.32.0.5', ['172.16.0.0/12']));
        self::assertTrue(TridentOrigin::matches('::1', ['::1/128']));
        self::assertTrue(TridentOrigin::matches('2001:db8::7', ['2001:db8::/32']));
        self::assertFalse(TridentOrigin::matches('10.0.0.1', ['2001:db8::/32']));
        self::assertFalse(TridentOrigin::matches('not-an-ip', ['0.0.0.0/0']));
        self::assertTrue(TridentOrigin::matches('192.168.1.200', ['192.168.1.128/25']));
        self::assertFalse(TridentOrigin::matches('192.168.1.100', ['192.168.1.128/25']));
        self::assertFalse(TridentOrigin::matches('10.0.0.1', []));
    }

    public function testMalformedCidrsMatchNothing(): void
    {
        foreach (['10.0.0.0/', '/abc', '10.0.0.0/abc', '10.0.0.0/-1', '10.0.0.0/33', '::/129', '/', ''] as $cidr) {
            self::assertFalse(TridentOrigin::matches('10.0.0.1', [$cidr]), "'$cidr' must not match");
            self::assertFalse(TridentOrigin::matches('8.8.8.8', [$cidr]), "'$cidr' must not match");
        }
    }

    public function testIpv4MappedIpv6PeerMatchesIpv4Ranges(): void
    {
        self::assertTrue(TridentOrigin::matches('::ffff:127.0.0.1', ['127.0.0.1']));
        self::assertTrue(TridentOrigin::matches('::ffff:172.21.0.5', ['172.16.0.0/12']));
        self::assertFalse(TridentOrigin::matches('::ffff:8.8.8.8', ['172.16.0.0/12']));
        self::assertTrue(TridentOrigin::matches('::ffff:10.1.2.3', ['::ffff:10.0.0.0/104']), 'mapped range written as IPv6 still works');
    }
}
