<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\PurgeClient;
use Qoliber\Trident\Delivery\Purger;
use Qoliber\Trident\Testing\FakeTransport;
use Qoliber\Trident\Testing\InMemoryOutboxStore;

/**
 * X02/X03 delivery against the in-memory outbox and a scripted admin API.
 */
final class PurgerTest extends TestCase
{
    private InMemoryOutboxStore $store;
    private FakeTransport $http;
    private int $now = 1_700_000_000;
    /** @var list<callable> */
    private array $deferred = [];

    protected function setUp(): void
    {
        $this->store = new InMemoryOutboxStore();
        $this->http = new FakeTransport();
    }

    /**
     * @param list<Instance> $instances
     */
    private function purger(array $instances, string $mode = 'soft'): Purger
    {
        return new Purger(
            $this->store,
            $instances,
            fn (Instance $i): PurgeClient => new PurgeClient($i, $this->http),
            $mode,
            function (callable $cb): void {
                $this->deferred[] = $cb;
            },
            fn (): int => $this->now
        );
    }

    private function endRequest(): void
    {
        foreach ($this->deferred as $cb) {
            $cb();
        }
        $this->deferred = [];
    }

    /** @return list<Instance> */
    private static function one(): array
    {
        return [new Instance('default', 'http://edge1:9301', 'tok')];
    }

    /** @return list<Instance> */
    private static function two(): array
    {
        return [new Instance('edge-1', 'http://edge1:9301', 'tok'), new Instance('edge-2', 'http://edge2:9301', 'tok2')];
    }

    public function testRecordedFirstDeliveredAtEndOfRequestThenRemoved(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1', 'shop']);
        self::assertCount(1, $this->store->rows, 'recorded before any HTTP');
        self::assertSame([], $this->http->requests, 'nothing sent during the request');

        $this->endRequest();
        self::assertSame([], $this->store->rows, 'acknowledged → removed');
        self::assertSame([['p_1', 'shop']], $this->http->purgedTags('edge1'));
        self::assertSame('Bearer tok', $this->http->requests[0]['headers']['Authorization']);
        self::assertSame('soft', json_decode((string) $this->http->requests[0]['body'], true)['mode']);
    }

    public function testHardModeIsSent(): void
    {
        $purger = $this->purger(self::one(), 'hard');
        $purger->purgeTags(['x']);
        $this->endRequest();
        self::assertSame('hard', json_decode((string) $this->http->requests[0]['body'], true)['mode']);
    }

    public function testSameTagsInOneProcessAreRecordedOnceAndDeliveryArmedOnce(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1', 'shop']);
        $purger->purgeTags(['p_1', 'shop', 'cat_7']);
        $purger->purgeTags(['p_1']);
        self::assertCount(2, $this->store->rows);
        self::assertSame(['p_1', 'shop', 'cat_7'], $this->store->tagsFor('default'));
        self::assertCount(1, $this->deferred);
        $this->endRequest();
        self::assertCount(1, $this->http->requests, 'rows merged into one purge request');
    }

    public function test401KeepsThePurgeAndBacksOff(): void
    {
        $this->http->answer('edge1', 401, '{"error":"Unauthorized"}');
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->endRequest();

        self::assertCount(1, $this->store->rows, 'not acknowledged → kept');
        $row = reset($this->store->rows);
        self::assertSame(1, $row['attempts']);
        self::assertSame('HTTP 401 — Unauthorized', $row['last_error']);
        self::assertSame($this->now + 1, $row['next_attempt_at']);

        $purger->drain();
        self::assertCount(1, $this->http->requests, 'a scheduled drain respects the backoff');
        $this->http->answer('edge1', 200);
        $report = $purger->drain(50, true);
        self::assertSame(1, $report->delivered, 'an operator drain does not');
        self::assertSame([], $this->store->rows);
    }

    public function testBackoffGrowsPerEntry(): void
    {
        $this->http->answer('edge1', 503, '');
        $purger = $this->purger(self::one());
        $purger->purgeTags(['a']);
        $this->endRequest();
        foreach ([2, 4, 8] as $expected) {
            $this->now = reset($this->store->rows)['next_attempt_at'];
            $purger->drain();
            self::assertSame($this->now + $expected, reset($this->store->rows)['next_attempt_at']);
        }
        self::assertSame(4, reset($this->store->rows)['attempts']);
    }

    public function testUnreachableInstanceIsTriedOncePerDrain(): void
    {
        $this->http->down('edge1');
        $purger = $this->purger(self::one());
        for ($i = 0; $i < 5; $i++) {
            $this->store->record('default', array_map(static fn (int $n): string => "b{$i}_{$n}", range(1, 1000)), $this->now, $this->now);
        }
        $report = $purger->drain();
        self::assertCount(1, $this->http->requests, 'a dead edge is not waited out five times');
        self::assertSame(1, $report->failed);
        self::assertStringStartsWith('no response', (string) $report->instances['default']['error']);
    }

    public function testThreeConsecutiveRejectionsStopTheDrain(): void
    {
        $this->http->answer('edge1', 429, '{"error":"slow down"}');
        $purger = $this->purger(self::one());
        for ($i = 0; $i < 6; $i++) {
            $this->store->record('default', array_map(static fn (int $n): string => "b{$i}_{$n}", range(1, 1000)), $this->now, $this->now);
        }
        $purger->drain();
        self::assertCount(3, $this->http->requests);
    }

    public function testMultiInstanceEachDeliveredAndRetriedOnItsOwn(): void
    {
        $this->http->down('edge2');
        $purger = $this->purger(self::two());
        $purger->purgeTags(['p_9']);
        self::assertCount(2, $this->store->rows, 'one row per instance');
        $this->endRequest();

        self::assertSame([['p_9']], $this->http->purgedTags('edge1'));
        self::assertSame([], $this->store->tagsFor('edge-1'), 'edge-1 delivered despite edge-2 being down');
        self::assertSame(['p_9'], $this->store->tagsFor('edge-2'));

        $this->http->answer('edge2', 200);
        $this->http->requests = [];
        $purger->drain(50, true);
        self::assertSame([], $this->http->purgedTags('edge1'), 'edge-1 is not purged again');
        self::assertSame([['p_9']], $this->http->purgedTags('edge2'));
        self::assertSame([], $this->store->rows);
        self::assertSame('Bearer tok2', $this->http->requests[0]['headers']['Authorization']);
    }

    public function testRowsOwedToARemovedInstanceAreKeptAndNotSent(): void
    {
        $this->store->record('edge-2', ['x'], $this->now, $this->now);
        $this->store->record('Edge-1', ['y'], $this->now, $this->now);
        $purger = $this->purger([new Instance('edge-1', 'http://edge1:9301', 't')]);
        $purger->drain(50, true);
        self::assertSame([], $this->http->requests);
        self::assertSame(['Edge-1' => 1, 'edge-2' => 1], $this->store->stats($this->now)['by_instance']);
        self::assertSame(1, $this->store->forget('edge-2'));
    }

    public function testNoInstanceRecordsNothing(): void
    {
        $purger = $this->purger([]);
        self::assertSame(0, $purger->purgeTags(['x']));
        self::assertSame([], $this->store->rows);
        self::assertSame([], $this->deferred);
        self::assertSame(0, $purger->drain()->delivered);
    }

    public function testMissingTableFallsBackToBestEffortDelivery(): void
    {
        $this->store->broken = true;
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        self::assertStringContainsString("doesn't exist", (string) $purger->recordError());
        $this->endRequest();
        self::assertSame([['p_1']], $this->http->purgedTags('edge1'));
    }

    public function testMoreThan1000TagsAreSplitAcrossRowsAndRequests(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(array_map(static fn (int $n): string => "t{$n}", range(1, 2500)));
        self::assertCount(3, $this->store->rows);
        $this->endRequest();
        self::assertSame([1000, 1000, 500], array_map('count', $this->http->purgedTags('edge1')));
    }

    public function testPartialAcknowledgementRemovesOnlyWhatWasAcknowledged(): void
    {
        $this->http->then('edge1', 200)->then('edge1', 500, '');
        $purger = $this->purger(self::one());
        $purger->purgeTags(array_map(static fn (int $n): string => "t{$n}", range(1, 1500)));
        $this->endRequest();
        self::assertCount(1, $this->store->rows);
        self::assertCount(500, reset($this->store->rows)['tags']);
    }

    /**
     * The WordPress race (no transaction around a product save): the status
     * transition records the purge before the new price is written, and a
     * concurrent drainer must not deliver it in that window.
     */
    public function testANewRowIsLeftToItsWriterUntilTheGraceExpires(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);                      // status transition
        $other = $this->purger(self::one());              // cron, another request
        self::assertSame(0, $other->drain()->delivered, 'not due for other drainers');
        self::assertSame(0, $other->drain(50, true)->delivered, 'not even for an operator drain');
        self::assertSame([], $this->http->requests);

        $purger->purgeTags(['p_1', 'shop']);              // woocommerce_update_product
        $this->endRequest();                              // the writer delivers its own rows
        self::assertSame([], $this->store->rows);
        self::assertSame([['p_1', 'shop']], $this->http->purgedTags('edge1'));
    }

    public function testAnAbandonedRowIsDeliveredByOthersAfterTheGrace(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->deferred = [];                              // the process died before shutdown
        $this->now += Purger::DEFAULT_GRACE;
        self::assertSame(1, $this->purger(self::one())->drain()->delivered);
    }

    public function testATagRecordedLongAgoIsRecordedAgain(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->now += intdiv(Purger::DEFAULT_GRACE, 2);  // long-running import
        $purger->purgeTags(['p_1']);
        self::assertCount(2, $this->store->rows, 'the first row may already be due for others');
    }

    public function testDeliveredTagsAreNotDeduplicatedAnyMore(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->endRequest();
        $purger->purgeTags(['p_1']);                      // a later change in the same process
        self::assertCount(1, $this->store->rows);
    }

    public function testOwnRowsDeliveredByOthersMeanwhileAreSkipped(): void
    {
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->store->rows = [];                           // delivered and removed elsewhere
        $this->endRequest();
        self::assertSame([], $this->http->requests);
    }

    public function testDeliveryNeverThrowsIntoShutdown(): void
    {
        $this->store = new class extends \Qoliber\Trident\Testing\InMemoryOutboxStore {
            public function byIds(array $ids): array
            {
                throw new \RuntimeException('MySQL server has gone away');
            }
        };
        $purger = $this->purger(self::one());
        $purger->purgeTags(['p_1']);
        $this->endRequest();
        self::assertStringContainsString('gone away', (string) $purger->recordError());
        self::assertCount(1, $this->store->rows, 'kept for cron');
    }

    /**
     * "Test connection" runs against a URL an admin typed. Whatever that server
     * answers must not come back to the page: it would make the button an
     * SSRF oracle for the internal network.
     */
    public function testStatusNeverEchoesServerControlledText(): void
    {
        $client = new PurgeClient(self::one()[0], $this->http);
        $this->http->then('edge1', 200, '{"version":"<script>x</script> secret-internal-banner","mode":"licensed"}');
        self::assertSame(['ok' => true, 'message' => 'Trident (unrecognised version), license: licensed'], $client->status());
        $this->http->then('edge1', 200, '{"version":"1.8.0","mode":"root:x:0:0"}');
        self::assertSame(['ok' => true, 'message' => 'Trident 1.8.0, license: unknown'], $client->status());
        $this->http->then('edge1', 500, '{"error":"db password is hunter2"}');
        self::assertSame(['ok' => false, 'message' => 'HTTP 500 — server error'], $client->status());
        $this->http->then('edge1', 200, '<html>internal wiki</html>');
        self::assertSame(['ok' => false, 'message' => 'HTTP 200 — not a Trident admin API'], $client->status());
        $this->http->answer('edge1', 0, '', 'cURL error 7: Failed to connect to 10.0.0.5 port 22: Connection refused');
        self::assertSame(['ok' => false, 'message' => 'no response'], $client->status());
    }

    public function testStatusCheck(): void
    {
        $this->http->then('edge1', 200, '{"status":"ok","version":"1.8.0","license":"valid","mode":"licensed"}');
        $client = new PurgeClient(self::one()[0], $this->http);
        self::assertSame(['ok' => true, 'message' => 'Trident 1.8.0, license: licensed'], $client->status());
        $this->http->answer('edge1', 401, '{"error":"Unauthorized"}');
        self::assertSame(['ok' => false, 'message' => 'HTTP 401 — token rejected'], $client->status());
    }
}
