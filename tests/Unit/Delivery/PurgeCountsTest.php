<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Drainer;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\PurgeAttempt;
use Qoliber\Trident\Delivery\PurgeClient;
use Qoliber\Trident\Testing\FakeTransport;
use Qoliber\Trident\Testing\InMemoryOutboxStore;

/**
 * Delivery reports how many cache entries each purge removed (1.5.0), and can
 * clear a whole instance, judged by the clear schema.
 */
final class PurgeCountsTest extends TestCase
{
    public function testAnAttemptCarriesThePurgedCountAndState(): void
    {
        $a = PurgeAttempt::fromAnswer(null, '{"purged":4,"mode":"soft","state":"applied"}');
        self::assertTrue($a->acknowledged());
        self::assertSame(4, $a->purged);
        self::assertSame('applied', $a->state);

        $deferred = PurgeAttempt::fromAnswer(null, '{"status":"deferred","state":"recorded"}');
        self::assertNull($deferred->purged, 'a deferred purge has not purged anything yet');
        self::assertSame('recorded', $deferred->state);

        self::assertNull(PurgeAttempt::fromAnswer('HTTP 500', 'oops')->purged);
    }

    public function testTheOldConstructorStillWorks(): void
    {
        $a = new PurgeAttempt(null);
        self::assertTrue($a->acknowledged());
        self::assertNull($a->purged);
        self::assertFalse((new PurgeAttempt('no response', true))->acknowledged());
    }

    public function testPurgeTagsReportsTheCount(): void
    {
        $http = (new FakeTransport())->answer('edge1', 200, '{"purged":6,"mode":"hard"}');
        $a = (new PurgeClient(new Instance('default', 'http://edge1:9301', 'tok'), $http))->purgeTags(['p_1'], 'hard');
        self::assertSame(6, $a->purged);
    }

    public function testClearIsJudgedByTheClearSchema(): void
    {
        $http = new FakeTransport();
        $client = new PurgeClient(new Instance('default', 'http://edge1:9301', 'tok'), $http);

        $http->answer('edge1', 200, '{"cleared":true,"entries_removed":9,"bytes_freed":100}');
        $ok = $client->clear();
        self::assertTrue($ok->acknowledged());
        self::assertSame(9, $ok->purged);
        self::assertSame('http://edge1:9301/admin/cache/clear', $http->requests[0]['url']);
        self::assertSame(['confirm' => true], json_decode((string) $http->requests[0]['body'], true));
        self::assertSame('Bearer tok', $http->requests[0]['headers']['Authorization']);

        $http->answer('edge1', 200, '{"purged":9,"mode":"hard"}');
        self::assertFalse($client->clear()->acknowledged(), 'a purge answer is not a clear');

        $http->down('edge1');
        $down = $client->clear();
        self::assertFalse($down->acknowledged());
        self::assertTrue($down->unreachable);
    }

    public function testTheDrainReportAddsUpThePurgedCounts(): void
    {
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())
            ->answer('edge1', 200, '{"purged":3,"mode":"soft"}')
            ->answer('edge2', 202, '{"status":"deferred","state":"recorded"}');
        $instances = [new Instance('edge-1', 'http://edge1:9301', 't1'), new Instance('edge-2', 'http://edge2:9301', 't2')];
        $store->record('edge-1', ['p_1'], 1_700_000_000, 1_700_000_000);
        $store->record('edge-2', ['p_1'], 1_700_000_000, 1_700_000_000);

        $report = (new Drainer($store, $instances, fn (Instance $i): PurgeClient => new PurgeClient($i, $http)))
            ->drain(50, 1_700_000_000);

        self::assertSame(2, $report->delivered);
        self::assertSame(3, $report->purged, 'the deferred purge counts as delivered, not as purged');
        self::assertSame(3, $report->instances['edge-1']['purged']);
        self::assertSame(0, $report->instances['edge-2']['purged']);
    }
}
