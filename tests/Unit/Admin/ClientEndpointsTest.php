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

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Response\CacheStatsResponse;
use Qoliber\Trident\Response\LaunchStatusResponse;
use Qoliber\Trident\Response\MemoryStatsResponse;
use Qoliber\Trident\Testing\FakeTransport;

/**
 * The request each operator call sends, checked against the engine's own
 * field names (trident-core/src/server/admin), and the real response shapes
 * the screens read.
 */
final class ClientEndpointsTest extends TestCase
{
    private FakeTransport $t;
    private TridentClient $c;

    protected function setUp(): void
    {
        $this->t = new FakeTransport();
        $this->c = TridentClient::forInstance(new Instance('edge', 'http://edge:9301', 'tok'), $this->t);
    }

    /**
     * @return array{method: string, url: string, body: mixed}
     */
    private function sent(): array
    {
        $r = $this->t->requests[count($this->t->requests) - 1];
        return ['method' => $r['method'], 'url' => $r['url'], 'body' => $r['body'] === null ? null : json_decode($r['body'], true)];
    }

    /**
     * The engine reads `pattern_type`. The client sent `type`, which the engine
     * ignored, so a regex tag pattern was purged as a wildcard.
     */
    public function testATagPatternNamesItsTypeTheWayTheEngineReadsIt(): void
    {
        $this->t->answer('edge', 200, '{"purged":0,"mode":"hard"}');
        $this->c->purgeTagPattern('^cat_\\d+$', 'regex');
        $sent = $this->sent();
        self::assertSame('http://edge:9301/admin/purge/tag/pattern', $sent['url']);
        self::assertSame(['pattern' => '^cat_\\d+$', 'pattern_type' => 'regex'], $sent['body']);
    }

    public function testForInstanceKeepsTheInstanceName(): void
    {
        self::assertSame('edge', $this->c->instance()->name);
    }

    public function testPurgeUrlSplitsAnAbsoluteUrl(): void
    {
        $this->c->purgeUrl('http://localhost:8480/shop/?a=1');
        self::assertSame(
            ['url' => '/shop/?a=1', 'host' => 'localhost:8480', 'scheme' => 'http', 'mode' => 'hard'],
            $this->sent()['body']
        );
    }

    public function testPurgeUrlKeepsAPathAsIs(): void
    {
        $this->c->purgeUrl('/shop/', true);
        self::assertSame(['url' => '/shop/', 'mode' => 'soft'], $this->sent()['body'], 'soft is explicit: else the engine default applies');
        $this->c->purgeUrlPattern(':shop.example:/p/', true);
        self::assertSame(['pattern' => ':shop.example:/p/', 'mode' => 'soft'], $this->sent()['body']);
    }

    public function testPurgeHostAndVary(): void
    {
        $this->c->purgeHost('shop.example');
        self::assertSame(['POST', 'http://edge:9301/admin/purge/host', ['host' => 'shop.example', 'mode' => 'hard']], array_values($this->sent()));
        $this->c->purgeVary('accept-encoding', 'gzip', true);
        self::assertSame(['header' => 'accept-encoding', 'value' => 'gzip', 'mode' => 'soft'], $this->sent()['body']);
    }

    public function testPurgePreviewSendsTheEngineSelectorField(): void
    {
        $this->c->purgePreview('^/shop/');
        self::assertSame(['url_pattern' => '^/shop/', 'sample_limit' => 20], $this->sent()['body']);
        $this->c->purgePreview('wc_shop', 'tag');
        self::assertSame(['tag' => 'wc_shop', 'sample_limit' => 20], $this->sent()['body']);
        $this->c->purgePreview('a, b,,c', 'tags');
        self::assertSame(['tags' => ['a', 'b', 'c'], 'sample_limit' => 20], $this->sent()['body']);
    }

    public function testCoverageSendsPathsHostAndScheme(): void
    {
        $this->t->answer('edge', 200, '{"total":2,"cached":1,"uncached":1,"percent_cached":50.0,"results":[{"url":"/shop/","cached":true},{"url":"/x/","cached":false}]}');
        $p = $this->c->coverage(['/shop/', '/x/'], 'localhost:8480', 'http');
        self::assertSame(['urls' => ['/shop/', '/x/'], 'host' => 'localhost:8480', 'scheme' => 'http'], $this->sent()['body']);
        self::assertSame(50.0, $p->float('percent_cached'));
        self::assertCount(2, $p->rows('results'));
    }

    public function testExplainSendsThePathAndAHostHeader(): void
    {
        $this->c->explain('http://localhost:8480/shop/');
        self::assertSame(['method' => 'GET', 'url' => '/shop/', 'detail' => false, 'headers' => ['host' => 'localhost:8480']], $this->sent()['body']);
    }

    public function testVariantsIsAQuery(): void
    {
        $this->c->cacheVariants('http://localhost:8480/shop/');
        self::assertSame('http://edge:9301/admin/cache/variants?url=http%3A%2F%2Flocalhost%3A8480%2Fshop%2F', $this->sent()['url'], 'absolute: the engine ignores a separate scheme here');
    }

    public function testLaunchUsesTheIdlessEndpoints(): void
    {
        $this->t->answer('edge', 200, '{"active":false,"state":"disabled"}');
        self::assertSame('disabled', $this->c->launchStatus()->status);
        self::assertSame('http://edge:9301/admin/launch/status', $this->sent()['url']);
        $this->c->launchAbort(null, 'rollback');
        self::assertSame(['POST', 'http://edge:9301/admin/launch/abort', ['reason' => 'rollback']], array_values($this->sent()));
        $this->c->launchComplete();
        self::assertSame('http://edge:9301/admin/launch/complete', $this->sent()['url']);
    }

    public function testReflect(): void
    {
        $this->c->reflectEnable('selective', '30m', '');
        self::assertSame(['level' => 'selective', 'duration' => '30m'], $this->sent()['body']);
        $this->c->reflectEnable();
        self::assertSame([], $this->sent()['body'], 'sent as {} — see ApiTest');
        $this->c->reflectDisable('replay');
        self::assertSame(['mode' => 'replay'], $this->sent()['body']);
    }

    public function testWarmer(): void
    {
        $this->c->warmerQueue(['http://localhost:8480/a/']);
        self::assertSame(['POST', 'http://edge:9301/admin/warmer/queue', ['urls' => ['http://localhost:8480/a/']]], array_values($this->sent()));
        $this->c->warmerCancel();
        self::assertSame('http://edge:9301/admin/warmer/cancel', $this->sent()['url']);
    }

    public function testDenoisers(): void
    {
        $this->c->denoiserQueryPin('utm_x', 'noise');
        self::assertSame(['param' => 'utm_x', 'class' => 'noise', 'host' => '*', 'path_prefix' => '/'], $this->sent()['body']);
        $this->c->denoiserPathPin('dead', 'shop.example', '/search/');
        self::assertSame(['status' => 'dead', 'host' => 'shop.example', 'path_prefix' => '/search/'], $this->sent()['body']);
        $this->c->denoiserReset('path');
        self::assertSame('http://edge:9301/admin/denoisers/path/reset', $this->sent()['url']);
        $this->expectException(\InvalidArgumentException::class);
        $this->c->denoiserReset('../cache/clear');
    }

    public function testListsSendTheEngineFilters(): void
    {
        $this->t->answer('edge', 200, '{"total":0,"entries":[],"tags":[]}');
        $this->c->cacheEntries(50, 100, null, 'hits', 'wc_shop');
        self::assertSame('http://edge:9301/admin/cache/entries?limit=50&offset=100&sort=hits&tag=wc_shop', $this->sent()['url']);
        $this->c->cacheTags(20, 0, null, 'name', 'wc_p_');
        self::assertSame('http://edge:9301/admin/cache/tags?limit=20&offset=0&sort=name&prefix=wc_p_', $this->sent()['url']);
    }

    public function testDiscoveryUsesTheNameQuery(): void
    {
        $this->t->answer('edge', 200, '{}');
        $this->c->discoveryRefresh('app pool');
        self::assertSame(['POST', 'http://edge:9301/admin/discovery/refresh?name=app+pool'], [$this->sent()['method'], $this->sent()['url']]);
    }

    public function testMemoryReadsTheEngineSnapshot(): void
    {
        $m = MemoryStatsResponse::fromArray([
            'snapshot' => [
                'categories' => [
                    ['category' => 'cache_entry_bodies', 'band' => 'resident', 'logical_bytes' => 1000],
                    ['category' => 'cache_entry_headers', 'band' => 'resident', 'logical_bytes' => 10],
                    ['category' => 'cache_entry_keys', 'band' => 'resident', 'logical_bytes' => 20],
                    ['category' => 'tag_index', 'band' => 'resident', 'logical_bytes' => 30],
                    ['category' => 'long_lived_tasks', 'band' => 'transient', 'logical_bytes' => 40],
                ],
                'allocator' => ['rss_bytes' => 29822976],
                'tracked_logical_bytes' => 1100,
            ],
        ]);
        self::assertSame(29822976, $m->rssBytes);
        self::assertSame(1000, $m->cacheBodyBytes);
        self::assertSame(30, $m->cacheMetadataBytes);
        self::assertSame(30, $m->tagIndexBytes);
        self::assertSame(40, $m->bufferBytes);
        self::assertSame(1100, $m->trackedBytes);
    }

    public function testStatsReadsTheEngineHitRatio(): void
    {
        $s = CacheStatsResponse::fromArray(['entries' => 9, 'hits' => 296, 'misses' => 232, 'passes' => 98, 'hit_ratio' => 56.06]);
        self::assertSame(296, $s->hits);
        self::assertSame(98, $s->passes);
        self::assertSame(56.06, $s->getHitRatioPercent());
        $computed = CacheStatsResponse::fromArray(['hits' => 3, 'misses' => 1]);
        self::assertSame(75.0, $computed->getHitRatioPercent());
    }

    public function testPreviewReadsTheEngineSample(): void
    {
        $this->t->answer('edge', 200, '{"would_purge":2,"would_free_bytes":84430,"sample":[{"url_key":"GET:http:localhost:8480:/shop/"},{"url_key":"GET:http:localhost:8480:/"}],"sample_truncated":true}');
        $preview = $this->c->purgePreview('wc_shop', 'tag');
        self::assertSame(2, $preview->wouldPurge);
        self::assertSame(84430, $preview->estimatedBytes);
        self::assertSame(['GET:http:localhost:8480:/shop/', 'GET:http:localhost:8480:/'], $preview->keys);
        self::assertTrue($preview->sampleTruncated);
    }

    public function testEntriesReadTheEngineShape(): void
    {
        $this->t->answer('edge', 200, '{"total":1,"offset":0,"limit":1,"has_more":false,"entries":[{"storage_key":"ab","url_key":"GET:http:localhost:8480:/shop/","status":"fresh","status_code":200,"size":84430,"ttl_remaining":3041,"age":5,"hits":2,"tags":["wc_shop"],"content_type":"text/html; charset=UTF-8"}]}');
        $item = $this->c->cacheEntries(1)->entries[0];
        self::assertSame('GET:http:localhost:8480:/shop/', $item->key);
        self::assertSame(84430, $item->contentLength);
        self::assertSame('text/html; charset=UTF-8', $item->contentType);

        $this->t->answer('edge', 200, '{"found":true,"storage_key":"ab","status":"fresh","ttl_remaining":3599,"age":0,"tags":["wc_shop"],"hits":0,"status_code":200,"content_length":84430,"vary":["Accept-Encoding"],"content_type":"text/html","compressed":false,"grace_remaining":89999}');
        $entry = $this->c->cacheEntry('/shop/', 'localhost:8480');
        self::assertSame(['Accept-Encoding'], $entry->vary);
        self::assertSame(89999, $entry->graceRemaining);
        self::assertFalse($entry->compressed);
    }

    public function testLaunchActionsReadTheEngineAnswer(): void
    {
        $this->t->answer('edge', 200, '{"success":true,"message":"Launch mode started","total_urls":42}');
        $started = $this->c->launchStart(['urls' => ['http://s/a']]);
        self::assertTrue($started->success);
        self::assertSame(42, $started->urlsTotal);
        self::assertSame('Launch mode started', $started->message);
        $this->t->answer('edge', 409, '{"error":"Launch already active","code":"LAUNCH_ACTIVE"}');
        try {
            $this->c->launchStart(['urls' => []]);
            self::fail('expected ApiError');
        } catch (\Qoliber\Trident\Admin\ApiError $e) {
            self::assertSame('LAUNCH_ACTIVE', $e->engineCode);
        }
    }

    public function testDiscoveryReadsTheEngineShape(): void
    {
        $this->t->answer('edge', 200, '{"count":1,"backends":[{"backend_name":"app","hostname":"app.internal","port":80,"method":"dns","addresses":["10.0.0.5:80","10.0.0.6:80"],"primary":"10.0.0.5:80","from_cache":false,"stats":{}}]}');
        $list = $this->c->discoveryList();
        self::assertSame(1, $list->total);
        self::assertSame('app', $list->backends[0]->name);
        self::assertSame(['10.0.0.5:80', '10.0.0.6:80'], $list->backends[0]->resolvedIps);
        $this->t->answer('edge', 200, '{"backend_name":"app","success":true,"address_count":2,"addresses":["10.0.0.5:80","10.0.0.6:80"]}');
        $refresh = $this->c->discoveryRefresh('app');
        self::assertSame('app', $refresh->name);
        self::assertCount(2, $refresh->ips);
    }

    public function testLaunchStatusReadsState(): void
    {
        self::assertSame('warming', LaunchStatusResponse::fromArray(['active' => true, 'state' => 'warming'])->status);
    }
}
