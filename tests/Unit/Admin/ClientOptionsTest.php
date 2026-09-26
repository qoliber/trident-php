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
use Qoliber\Trident\Purge\PurgeRequest;
use Qoliber\Trident\Testing\FakeTransport;

/**
 * The 1.5.0 client calls: every option the engine accepts, sent exactly as
 * the engine reads it.
 */
final class ClientOptionsTest extends TestCase
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

    public function testPurgeSendsTheBuiltRequest(): void
    {
        $this->t->answer('edge', 200, '{"purged":3,"mode":"soft","state":"applied"}');
        $r = $this->c->purge(PurgeRequest::tags(['cat_5'])->excluding(['home'])->soft());

        self::assertSame(['method' => 'POST', 'url' => 'http://edge:9301/admin/purge/tags', 'body' => [
            'tags' => ['cat_5'], 'match_mode' => 'any', 'mode' => 'soft', 'exclude_tags' => ['home'],
        ]], $this->sent());
        self::assertTrue($r->isAcknowledged());
        self::assertSame(3, $r->getPurgedCount());
        self::assertSame('applied', $r->raw()['state']);
    }

    public function testATagPurgeSendsAModeOnlyWhenOneWasChosen(): void
    {
        $this->t->answer('edge', 200, '{"purged":1,"mode":"soft"}');
        $this->c->purge(PurgeRequest::tag('cat_5'));
        self::assertSame(['tag' => 'cat_5'], $this->sent()['body'], 'the operator default applies');
        $this->c->purge(PurgeRequest::tag('cat_5')->hard());
        self::assertSame(['tag' => 'cat_5', 'mode' => 'hard'], $this->sent()['body']);
    }

    public function testClearCacheIsTypedOnTheClearSchema(): void
    {
        $this->t->answer('edge', 200, '{"cleared":true,"entries_removed":7,"bytes_freed":4096}');
        $r = $this->c->clearCache();
        self::assertSame(['confirm' => true], $this->sent()['body']);
        self::assertTrue($r->cleared);
        self::assertTrue($r->isAcknowledged());
        self::assertSame(7, $r->entriesRemoved);
        self::assertSame(4096, $r->bytesFreed);

        $this->t->answer('edge', 200, '{"cleared":false}');
        self::assertFalse($this->c->clearCache()->isAcknowledged());
    }

    public function testExplainRequestSendsHeadersAndCookies(): void
    {
        $this->t->answer('edge', 200, '{"verdict":"miss","cacheable":true,"visibility":"public","reason":"ok"}');
        $p = $this->c->explainRequest(
            'https://shop.example.com/cart/?x=1',
            'get',
            ['Accept-Language' => 'pl', 'Cookie' => 'a=1'],
            ['woocommerce_items_in_cart' => '1'],
            true
        );
        self::assertSame(['method' => 'POST', 'url' => 'http://edge:9301/admin/explain', 'body' => [
            'method' => 'GET',
            'url' => '/cart/?x=1',
            'detail' => true,
            'headers' => [
                'accept-language' => 'pl',
                'cookie' => 'a=1; woocommerce_items_in_cart=1',
                'host' => 'shop.example.com',
            ],
        ]], $this->sent());
        self::assertTrue($p->bool('cacheable'));
    }

    public function testAnExplicitHostHeaderWins(): void
    {
        $this->t->answer('edge', 200, '{}');
        $this->c->explainRequest('http://a.example/', 'GET', ['Host' => 'b.example']);
        self::assertSame(['host' => 'b.example'], $this->sent()['body']['headers']);
    }

    public function testExplainKeepsItsOldShape(): void
    {
        $this->t->answer('edge', 200, '{}');
        $this->c->explain('http://a.example/p');
        self::assertSame(['method' => 'GET', 'url' => '/p', 'detail' => false, 'headers' => ['host' => 'a.example']], $this->sent()['body']);
    }

    public function testDenoiserPinsAreValidatedBeforeAnythingIsSent(): void
    {
        foreach (
            [
            fn () => $this->c->denoiserQueryPin('utm_source', 'noisy'),
            fn () => $this->c->denoiserQueryPin(' ', 'noise'),
            fn () => $this->c->denoiserPathPin('gone'),
            ] as $call
        ) {
            try {
                $call();
                self::fail('expected InvalidRequest');
            } catch (\Qoliber\Trident\Exception\InvalidRequest $e) {
                self::assertStringContainsString('Denoiser', $e->getMessage());
            }
        }
        self::assertSame([], $this->t->requests, 'an invalid pin never reaches the engine');
    }

    public function testDenoiserPinsSendTheEngineFields(): void
    {
        $this->t->answer('edge', 200, '{"pinned":true}');
        $this->c->denoiserQueryPin('utm_source', 'noise', 'shop.example.com', '/sale/');
        self::assertSame(['param' => 'utm_source', 'class' => 'noise', 'host' => 'shop.example.com', 'path_prefix' => '/sale/'], $this->sent()['body']);
        $this->c->denoiserPathPin('dead', '*', '/old/');
        self::assertSame(['status' => 'dead', 'host' => '*', 'path_prefix' => '/old/'], $this->sent()['body']);
    }

    public function testZoneAndScopeDeletes(): void
    {
        $this->t->answer('edge', 200, '{"deleted":true}');
        $this->c->denoiserPathZoneDelete('shop.example.com', '/old/');
        self::assertSame(['method' => 'DELETE', 'url' => 'http://edge:9301/admin/denoisers/path/zone', 'body' => ['host' => 'shop.example.com', 'path_prefix' => '/old/']], $this->sent());
        $this->c->denoiserQueryScopeDelete();
        self::assertSame(['method' => 'DELETE', 'url' => 'http://edge:9301/admin/denoisers/query/scope', 'body' => ['host' => '*', 'path_prefix' => '/']], $this->sent());
    }

    public function testWafExport(): void
    {
        $this->t->answer('edge', 200, '{"query_noise_params":[],"dead_zones":[],"format":"trident-waf-v1"}');
        self::assertSame('trident-waf-v1', $this->c->wafExport()->string('format'));
        self::assertSame(['method' => 'GET', 'url' => 'http://edge:9301/admin/denoisers/export/waf', 'body' => null], $this->sent());
    }

    public function testEveryTypedReadKeepsItsAnswer(): void
    {
        $this->t->answer('edge', 200, '{"status":"healthy","uptime_seconds":5,"future":1}');
        $h = $this->c->health();
        self::assertSame(5, $h->getUptime());
        self::assertSame(1, $h->raw()['future']);
    }
}
