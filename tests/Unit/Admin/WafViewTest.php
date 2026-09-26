<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Admin\WafView;

final class WafViewTest extends TestCase
{
    public function testDeadZonesOfThisShopAndEveryHostOnly(): void
    {
        $export = ['format' => 'trident-waf-v1', 'dead_zones' => [
            ['zone_key' => 'shop.example:8580|/search', 'action' => 'block'],
            ['zone_key' => '*|/wp-login.php', 'action' => 'block'],
            ['zone_key' => 'other.example|/private', 'action' => 'block'],
            ['zone_key' => 'no-bar', 'action' => 'block'],
            'not an array',
        ]];

        self::assertSame([
            ['host' => 'shop.example:8580', 'prefix' => '/search', 'action' => 'block', 'inert' => false],
            ['host' => '*', 'prefix' => '/wp-login.php', 'action' => 'block', 'inert' => true],
        ], WafView::deadZones($export, ['SHOP.example:8580']));
    }

    public function testEveryHostOfTheShopCounts(): void
    {
        $export = ['dead_zones' => [
            ['zone_key' => 'a.example|/x', 'action' => 'block'],
            ['zone_key' => 'b.example|/y', 'action' => 'block'],
            ['zone_key' => 'c.example|/z', 'action' => 'block'],
        ]];

        self::assertSame(['/x', '/y'], array_column(WafView::deadZones($export, ['a.example', 'b.example']), 'prefix'));
    }

    public function testAPortIsPartOfTheHost(): void
    {
        $export = ['dead_zones' => [
            ['zone_key' => 'shop.example|/old', 'action' => 'reject'],
            ['zone_key' => '*|/wp-json-v0', 'action' => 'reject'],
            ['zone_key' => 'shop.example:8080|/x', 'action' => 'reject'],
        ]];

        self::assertSame(['/wp-json-v0', '/x'], array_column(WafView::deadZones($export, ['shop.example:8080']), 'prefix'));
    }

    public function testMalformedRowsAreSkippedNotFatal(): void
    {
        self::assertSame([], WafView::deadZones(['dead_zones' => ['x', ['zone_key' => 5], ['zone_key' => 'nobar']]], ['shop.example']));
        self::assertSame([], WafView::noise(['x', ['scope_key' => 'shop.example|/', 'noise' => 'x']], ['shop.example']));
        self::assertSame([], WafView::deadZones(['dead_zones' => 'not a list'], ['shop.example']));
    }

    public function testNoiseComesFromThisShopsScopesSorted(): void
    {
        $scopes = [
            ['scope_key' => 'shop.example|/', 'noise' => ['utm_x', 'fbclid']],
            ['scope_key' => 'other.example|/', 'noise' => ['secret']],
            ['scope_key' => '*|/', 'noise' => ['gclid', 42]],
        ];

        self::assertSame([
            ['param' => 'fbclid', 'scope' => 'shop.example|/', 'inert' => false],
            ['param' => 'gclid', 'scope' => '*|/', 'inert' => true],
            ['param' => 'utm_x', 'scope' => 'shop.example|/', 'inert' => false],
        ], WafView::noise($scopes, ['shop.example']));
    }

    public function testRowHostsCompareCaseInsensitively(): void
    {
        $export = ['dead_zones' => [['zone_key' => 'SHOP.Example|/x', 'action' => 'block']]];
        self::assertSame(['/x'], array_column(WafView::deadZones($export, ['shop.example']), 'prefix'));
    }

    public function testNoHostsShowsOnlyTheInertWildcardRows(): void
    {
        $export = ['dead_zones' => [['zone_key' => 'shop.example|/x', 'action' => 'block'], ['zone_key' => '*|/y', 'action' => 'block']]];
        self::assertSame([['host' => '*', 'prefix' => '/y', 'action' => 'block', 'inert' => true]], WafView::deadZones($export, []));
        self::assertSame([], WafView::noise([['scope_key' => 'shop.example|/', 'noise' => ['a']]], []));
    }
}
