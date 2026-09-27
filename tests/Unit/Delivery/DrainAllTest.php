<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Drainer;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\PurgeClient;
use Qoliber\Trident\Testing\FakeTransport;
use Qoliber\Trident\Testing\InMemoryOutboxStore;

/**
 * drainAll(): "deliver now" for operators (after an incident) and test suites —
 * every row, whatever its due time: a scheduled second delivery, a backstop
 * row, a row still in its writer's grace, a row in backoff.
 */
final class DrainAllTest extends TestCase
{
    private const NOW = 1_700_000_000;

    public function testEveryRowIsDeliveredWhateverItsDueTime(): void
    {
        $store = new InMemoryOutboxStore();
        $store->record('edge', ['due'], self::NOW, self::NOW);
        $store->record('edge', ['in_grace'], self::NOW, self::NOW + 120);
        $store->record('edge', ['second_delivery'], self::NOW, self::NOW + 10);
        $store->record('edge', ['backstop'], self::NOW, self::NOW + 360);
        $http = (new FakeTransport())->answer('edge', 200, '{"purged":1,"mode":"soft"}');
        $drainer = new Drainer($store, [new Instance('edge', 'http://edge:9301', 't')], fn (Instance $i): PurgeClient => new PurgeClient($i, $http));

        self::assertSame(1, $drainer->drain(50, self::NOW)->delivered, 'the ordinary drain takes only the due row');
        $report = $drainer->drainAll(50, self::NOW);
        self::assertSame(3, $report->delivered, 'drainAll takes the rest');
        self::assertSame([], $store->due(50, PHP_INT_MAX, true, ['edge']));
    }

    public function testAFailureIsRetriedOnTheRealClockNotTheFarFuture(): void
    {
        $store = new InMemoryOutboxStore();
        $store->record('edge', ['backstop'], self::NOW, self::NOW + 360);
        $http = (new FakeTransport())->answer('edge', 503, '{"error":"down"}');
        $drainer = new Drainer($store, [new Instance('edge', 'http://edge:9301', 't')], fn (Instance $i): PurgeClient => new PurgeClient($i, $http));

        self::assertSame(1, $drainer->drainAll(50, self::NOW)->failed);
        self::assertCount(1, $store->due(50, self::NOW + 3600, false, ['edge']), 'the failed row is due again within the hour');
    }
}
