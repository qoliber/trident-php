<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\ApiHostAllowlist;
use Qoliber\Trident\Delivery\Instance;

final class ApiHostAllowlistTest extends TestCase
{
    public function testOnlyListedHostsAreKeptExactly(): void
    {
        $instances = [
            new Instance('a', 'http://trident:9301', 't'),
            new Instance('b', 'http://10.0.0.12:9301', 't'),
            new Instance('c', 'http://10.0.0.12:9302', 't'),
            new Instance('d', 'http://trident.evil:9301', 't'),
            new Instance('e', 'http://eviltrident:9301', 't'),
        ];
        [$kept, $errors] = ApiHostAllowlist::filter($instances, 'Trident, 10.0.0.12:9301', 'TRIDENT_ALLOWED_API_HOSTS');

        self::assertSame(['a', 'b'], array_map(static fn (Instance $i): string => $i->name, $kept));
        self::assertCount(3, $errors);
        self::assertStringContainsString('not in TRIDENT_ALLOWED_API_HOSTS', $errors[0]);
    }

    public function testAnEmptyListAllowsEverything(): void
    {
        $instances = [new Instance('a', 'http://anything:9301', 't')];

        self::assertSame([$instances, []], ApiHostAllowlist::filter($instances, ''));
        self::assertSame([$instances, []], ApiHostAllowlist::filter($instances, []));
    }

    public function testIpv6TrailingDotsAndCase(): void
    {
        $instances = [
            new Instance('v6', 'http://[::1]:9301', 't'),
            new Instance('dot', 'http://trident.:9301', 't'),
            new Instance('upper', 'http://TRIDENT:9302', 't'),
        ];
        [$kept] = ApiHostAllowlist::filter($instances, '[::1]:9301, trident., Trident:9302');
        self::assertSame(['v6', 'dot', 'upper'], array_map(static fn (Instance $i): string => $i->name, $kept));
        [$kept] = ApiHostAllowlist::filter($instances, '::1');
        self::assertSame(['v6'], array_map(static fn (Instance $i): string => $i->name, $kept));
        [$kept] = ApiHostAllowlist::filter($instances, '[::1]:9999');
        self::assertSame([], $kept);
    }
}
