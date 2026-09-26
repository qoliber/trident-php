<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Response\CacheStatsResponse;
use Qoliber\Trident\Response\ConnectionsResponse;
use Qoliber\Trident\Response\HealthResponse;
use Qoliber\Trident\Response\LatencyStatsResponse;
use Qoliber\Trident\Response\LaunchStatusResponse;
use Qoliber\Trident\Response\MemoryStatsResponse;
use Qoliber\Trident\Response\ProtectionStatsResponse;

/**
 * The answers below were captured from the Trident 1.8.0 release candidate
 * (1650438f). Each field here used to parse to zero or null because the
 * library read a different name than the engine sends (1.5.0).
 */
final class EngineShapeTest extends TestCase
{
    public function testHealthUptimeIsUptimeSeconds(): void
    {
        $h = HealthResponse::fromArray(['status' => 'healthy', 'uptime_seconds' => 18635]);
        self::assertTrue($h->isHealthy());
        self::assertSame(18635, $h->getUptime());
        self::assertSame(42, HealthResponse::fromArray(['uptime' => 42])->getUptime(), 'the old name still wins');
    }

    public function testStatsCarriesEveryCounter(): void
    {
        $s = CacheStatsResponse::fromArray([
            'entries' => 5, 'memory_used' => 103590, 'max_memory' => 256000000, 'evictions' => 0,
            'evicted_bytes' => 0, 'memory_usage_percent' => 0.04, 'compressions_total' => 3,
            'current_compressed_entries' => 2, 'compression_bytes_original' => 0,
            'compression_bytes_compressed' => 0, 'compression_ratio' => 1.0, 'compression_bytes_saved' => 0,
            'tag_index_memory_bytes' => 6948, 'unique_tags' => 20, 'tag_indexed_keys' => 5,
            'url_indexed_keys' => 4, 'hits' => 288, 'misses' => 234, 'passes' => 135,
            'hit_ratio' => 55.17,
        ]);
        self::assertSame(2, $s->compressedEntries);
        self::assertSame(3, $s->compressionsTotal);
        self::assertSame(5, $s->tagIndexedKeys);
        self::assertSame(4, $s->urlIndexedKeys);
        self::assertSame(4, $s->indexedKeys, 'indexed_keys keeps its url_indexed_keys fallback');
        self::assertSame(288, $s->hits);
        self::assertSame(55.17, $s->hitRatio);
    }

    public function testProtectionUsesTheEngineNamesAndKeysBackendsByName(): void
    {
        $p = ProtectionStatsResponse::fromArray([
            'protection_enabled' => true,
            'backends' => ['default' => [
                'acquired_total' => 12, 'timeouts_total' => 1, 'queue_full_total' => 2,
                'stale_served_total' => 3, 'queue_length' => 4, 'active_connections' => 5,
                'avg_queue_wait_ms' => 1.5, 'p99_queue_wait_ms' => 9,
            ]],
            'total_acquired' => 12, 'total_timeouts' => 1, 'total_queue_full' => 2, 'total_stale_served' => 3,
        ]);
        self::assertTrue($p->isEnabled());
        self::assertSame([12, 1, 2, 3], [$p->totalAcquired, $p->totalTimeouts, $p->totalQueueFull, $p->totalStaleServed]);
        self::assertCount(1, $p->backends);
        $b = $p->backends['default'];
        self::assertSame('default', $b->name, 'the map key is the backend name');
        self::assertSame([12, 1, 2, 3, 4, 5], [$b->acquiredTotal, $b->timeoutsTotal, $b->queueFullTotal, $b->staleServedTotal, $b->queueLength, $b->activeConnections]);
        self::assertSame(1.5, $b->avgQueueWaitMs);
        self::assertSame(9.0, $b->p99QueueWaitMs);
    }

    public function testMemoryCarriesFootprintPercentiles(): void
    {
        $m = MemoryStatsResponse::fromArray([
            'snapshot' => ['categories' => [], 'allocator' => ['rss_bytes' => 32362496], 'tracked_logical_bytes' => 210712],
            'footprint_percentiles' => ['count' => 5, 'p50' => 2539, 'p95' => 86798, 'p99' => 86798],
        ]);
        self::assertSame(32362496, $m->rssBytes);
        self::assertSame(['count' => 5, 'p50' => 2539, 'p95' => 86798, 'p99' => 86798], $m->footprintPercentiles);
    }

    public function testLaunchStatusCarriesActive(): void
    {
        $l = LaunchStatusResponse::fromArray(['active' => true, 'state' => 'warming']);
        self::assertTrue($l->active);
        self::assertTrue($l->isWarming());
        self::assertFalse(LaunchStatusResponse::fromArray(['active' => false, 'state' => 'disabled'])->active);
    }

    public function testConnectionRowsCarryTheEngineCounters(): void
    {
        $c = ConnectionsResponse::fromArray([
            'backends' => [['name' => 'default', 'healthy' => true, 'active' => 1, 'idle' => 4, 'max' => 32,
                'queued' => 2, 'total_created' => 7, 'total_reused' => 413]],
            'total_active' => 1, 'total_idle' => 4,
        ]);
        $b = $c->backends[0];
        self::assertSame(2, $b->waitingRequests, 'the engine calls it `queued`');
        self::assertSame(32, $b->maxConnections);
        self::assertTrue($b->healthy);
        self::assertSame(7, $b->totalCreated);
        self::assertSame(413, $b->totalReused);
    }

    public function testLatencySampleCountIsInsideLatency(): void
    {
        $l = LatencyStatsResponse::fromArray(['latency' => ['count' => 660, 'p50_ms' => 0.0, 'p99_ms' => 146.0]]);
        self::assertSame(660, $l->sampleCount);
    }
}
