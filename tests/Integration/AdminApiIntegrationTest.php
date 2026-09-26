<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Admin\ApiError;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Purge\PurgeRequest;
use Qoliber\Trident\TridentFactory;

/**
 * The 1.5.0 client against a live engine: the typed fields equal what the
 * engine sent, every purge option is accepted and echoed, a clear is typed,
 * explain takes the full request context, and denoiser pins round-trip.
 */
final class AdminApiIntegrationTest extends TestCase
{
    /** A pin needs a real host (the engine keys pins by the request host). */
    private const PIN_HOST = 'lib-it.example';

    private TridentClient $client;

    protected function setUp(): void
    {
        $this->client = TridentFactory::createClient(
            getenv('TRIDENT_ADMIN_URL') ?: 'http://trident:9100',
            getenv('TRIDENT_ADMIN_KEY') ?: 'test-admin-key-12345'
        );
    }

    public function testTypedStatsEqualTheEngineAnswer(): void
    {
        $s = $this->client->stats();
        $raw = $s->raw();
        self::assertArrayHasKey('hit_ratio', $raw, 'raw() is the whole answer');
        self::assertSame((int) $raw['hits'], $s->hits);
        self::assertSame((int) $raw['misses'], $s->misses);
        self::assertEqualsWithDelta((float) $raw['hit_ratio'], (float) $s->hitRatio, 0.0001);
        self::assertSame((int) ($raw['current_compressed_entries'] ?? 0), $s->compressedEntries);
        self::assertSame((int) ($raw['compressions_total'] ?? 0), $s->compressionsTotal);
        self::assertSame((int) ($raw['tag_indexed_keys'] ?? 0), $s->tagIndexedKeys);
    }

    public function testHealthUptimeIsTheEngineUptime(): void
    {
        $h = $this->client->health();
        self::assertArrayHasKey('uptime_seconds', $h->raw());
        self::assertSame((int) $h->raw()['uptime_seconds'], $h->getUptime());
        self::assertGreaterThan(0, (int) $h->getUptime());
    }

    public function testProtectionUsesTheEngineFields(): void
    {
        $p = $this->client->protectionStats();
        self::assertSame((bool) $p->raw()['protection_enabled'], $p->isEnabled());
        self::assertSame((int) $p->raw()['total_acquired'], $p->totalAcquired);
        foreach ($p->backends as $b) {
            self::assertNotSame('', $b->name, 'backend rows are named after their map key');
        }
    }

    public function testMemoryCarriesFootprintPercentiles(): void
    {
        $m = $this->client->memoryStats();
        self::assertSame($m->raw()['footprint_percentiles'] ?? null, $m->footprintPercentiles);
    }

    public function testEveryTypedReadKeepsItsAnswer(): void
    {
        $reads = [
            'health' => fn () => $this->client->health(),
            'ready' => fn () => $this->client->ready(),
            'stats' => fn () => $this->client->stats(),
            'tagStats' => fn () => $this->client->tagStats(),
            'refreshQueue' => fn () => $this->client->refreshQueue(),
            'latencyStats' => fn () => $this->client->latencyStats(),
            'errorStats' => fn () => $this->client->errorStats(),
            'backends' => fn () => $this->client->backends(),
            'bans' => fn () => $this->client->bans(),
            'config' => fn () => $this->client->config(),
            'rules' => fn () => $this->client->rules(),
            'protectionStats' => fn () => $this->client->protectionStats(),
            'memoryStats' => fn () => $this->client->memoryStats(),
            'connections' => fn () => $this->client->connections(),
            'discoveryList' => fn () => $this->client->discoveryList(),
            'launchStatus' => fn () => $this->client->launchStatus(),
            'topUrls' => fn () => $this->client->topUrls(5),
            'cacheEntries' => fn () => $this->client->cacheEntries(5),
            'cacheTags' => fn () => $this->client->cacheTags(5),
        ];
        foreach ($reads as $name => $read) {
            self::assertNotSame([], $read()->raw(), $name . ' keeps the engine answer');
        }
    }

    public function testTagPurgesStateTheirMode(): void
    {
        foreach (['soft', 'hard'] as $mode) {
            $r = $this->client->purge(PurgeRequest::tag('lib15.' . $mode)->{$mode}());
            self::assertTrue($r->isAcknowledged(), $mode . ': ' . (string) $r->failure);
            self::assertSame($mode, $r->mode, 'the engine applied the mode that was sent');
        }
    }

    public function testTagsPurgeWithExclusionsAndMatchAll(): void
    {
        $r = $this->client->purge(PurgeRequest::tags(['lib15.a', 'lib15.b'])->matchAll()->excluding(['lib15.keep'])->soft());
        self::assertTrue($r->isAcknowledged(), (string) $r->failure);
        self::assertSame('soft', $r->mode);
    }

    public function testRegexTagPatternIsAccepted(): void
    {
        $r = $this->client->purge(PurgeRequest::pattern('^lib15_\d+$', PurgeRequest::PATTERN_REGEX)->hard());
        self::assertTrue($r->isAcknowledged(), (string) $r->failure);
        self::assertSame('hard', $r->mode);
    }

    public function testAnUnsetModeLeavesTheOperatorDefault(): void
    {
        $r = $this->client->purge(PurgeRequest::tag('lib15.default'));
        self::assertTrue($r->isAcknowledged(), (string) $r->failure);
        self::assertContains($r->mode, ['soft', 'hard'], 'the engine applied its admin.default_purge_mode');
    }

    public function testUrlPurgeThroughPurge(): void
    {
        $r = $this->client->purge(PurgeRequest::url('http://lib15.invalid/nothing-here')->soft());
        self::assertTrue($r->isAcknowledged(), (string) $r->failure);
    }

    public function testClearCacheIsTyped(): void
    {
        $c = $this->client->clearCache();
        self::assertTrue($c->cleared);
        self::assertTrue($c->isAcknowledged());
        self::assertGreaterThanOrEqual(0, $c->entriesRemoved);
        self::assertSame($c->raw()['entries_removed'], $c->entriesRemoved);
    }

    public function testExplainTakesTheFullRequestContext(): void
    {
        $p = $this->client->explainRequest('http://lib15.invalid/some/page', 'GET', ['Accept-Language' => 'pl'], ['lib15' => '1'], true);
        self::assertContains($p->string('verdict'), ['hit', 'miss', 'pass']);
        self::assertIsBool($p->get('cacheable'));
        self::assertNotSame('', $p->string('reason'));
    }

    public function testWafExport(): void
    {
        self::assertSame('trident-waf-v1', $this->client->wafExport()->string('format'));
    }

    public function testQueryDenoiserPinRoundTrip(): void
    {
        try {
            $pin = $this->client->denoiserQueryPin('lib15_param', 'noise', self::PIN_HOST, '/lib15/');
        } catch (ApiError $e) {
            if ($e->status() === 503) {
                self::markTestSkipped('The query denoiser is not enabled on this engine.');
            }
            throw $e;
        }
        self::assertNotSame([], $pin->raw());
        $this->client->denoiserQueryUnpin('lib15_param', self::PIN_HOST, '/lib15/');
        $this->client->denoiserQueryScopeDelete(self::PIN_HOST, '/lib15/');
        $this->addToAssertionCount(1);
    }

    public function testPathDenoiserPinRoundTrip(): void
    {
        try {
            $pin = $this->client->denoiserPathPin('dead', self::PIN_HOST, '/lib15-old/');
        } catch (ApiError $e) {
            if ($e->status() === 503) {
                self::markTestSkipped('The path denoiser is not enabled on this engine.');
            }
            throw $e;
        }
        self::assertSame('dead', $pin->string('status'));
        $this->client->denoiserPathUnpin(self::PIN_HOST, '/lib15-old/');
        $gone = $this->client->denoiserPathZoneDelete(self::PIN_HOST, '/lib15-old/');
        self::assertTrue($gone->bool('deleted'));
    }
}
