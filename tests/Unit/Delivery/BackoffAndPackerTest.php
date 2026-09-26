<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Backoff;
use Qoliber\Trident\Delivery\OutboxEntry;
use Qoliber\Trident\Delivery\Packer;

final class BackoffAndPackerTest extends TestCase
{
    public function testBackoffDoublesThenCapsAndNeverGivesUp(): void
    {
        $delays = array_map([Backoff::class, 'delay'], range(1, 12));
        self::assertSame([1, 2, 4, 8, 16, 32, 64, 128, 256, 300, 300, 300], $delays);
        self::assertSame(300, Backoff::delay(10000));
        self::assertSame(1000 + 4, Backoff::nextAttemptAt(3, 1000));
        self::assertSame(1, Backoff::delay(0));
    }

    public function testPacksEntriesInOrderUnderTheTagLimit(): void
    {
        $entries = [
            new OutboxEntry(1, 'a', ['t1', 't2'], 0),
            new OutboxEntry(2, 'a', ['t2', 't3'], 0),
            new OutboxEntry(3, 'a', ['t4', 't5'], 0),
        ];
        $requests = Packer::pack($entries, 3);
        self::assertCount(2, $requests);
        self::assertSame(['t1', 't2', 't3'], $requests[0]['tags']);
        self::assertSame([1, 2], array_map(static fn (OutboxEntry $e): int => $e->id, $requests[0]['entries']));
        self::assertSame(['t4', 't5'], $requests[1]['tags']);
    }

    public function testDefaultLimitIs1000UniqueTags(): void
    {
        $entries = [];
        for ($i = 0; $i < 25; $i++) {
            $entries[] = new OutboxEntry($i + 1, 'a', array_map(static fn (int $n): string => "t{$i}_{$n}", range(1, 100)), 0);
        }
        $requests = Packer::pack($entries);
        self::assertCount(3, $requests);
        foreach ($requests as $request) {
            self::assertLessThanOrEqual(Packer::MAX_TAGS_PER_REQUEST, count($request['tags']));
        }
    }

    public function testAnOversizedSingleEntryIsStillSent(): void
    {
        self::assertCount(1, Packer::pack([new OutboxEntry(1, 'a', ['a', 'b', 'c', 'd'], 0)], 2));
    }

    public function testChunkDeduplicates(): void
    {
        $chunks = Packer::chunk(array_merge(['x', 'x'], array_map('strval', range(1, 1500))));
        self::assertCount(2, $chunks);
        self::assertCount(1000, $chunks[0]);
        self::assertCount(501, $chunks[1]);
        self::assertSame([], Packer::chunk([]));
    }
}
