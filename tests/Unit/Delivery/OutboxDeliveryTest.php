<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Qoliber\Trident\Config\Settings;
use Qoliber\Trident\Config\SettingsResolver;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\OutboxDelivery;
use Qoliber\Trident\Tags\TagPolicy;
use Qoliber\Trident\Testing\FakeTransport;
use Qoliber\Trident\Testing\InMemoryOutboxStore;

/**
 * The platform-neutral delivery façade (1.7.1), shared by trident-symfony and
 * the PrestaShop module: settings resolution, the tag policy on both sides,
 * record → flush → acknowledge, and the direct send when the outbox fails.
 */
final class OutboxDeliveryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private static function settings(string $prefix = ''): Settings
    {
        return SettingsResolver::resolve(
            ['TRIDENT_INSTANCES' => '{"edge":{"api_url":"http://edge:9301","api_token":"t"}}', 'TRIDENT_TAG_PREFIX' => $prefix],
            []
        );
    }

    public function testARecordedPurgeIsDeliveredAndRemovedOnAcknowledgement(): void
    {
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())->answer('edge', 200);
        $delivery = new OutboxDelivery($store, self::settings('s1_'), new TagPolicy('s1_'), $http, null, static fn (): int => self::NOW, null, 0);

        self::assertSame(1, $delivery->recordTags(['p_1', 'c_2']));
        $report = $delivery->flush();

        self::assertSame(1, $report->delivered);
        self::assertSame(['s1_c_2', 's1_p_1', 's1_tag_overflow'], self::sorted(array_merge(...$http->purgedTags('edge'))), 'the purge also names the overflow tag, so a page whose header was truncated is still purged');
        self::assertSame([], $store->due(50, PHP_INT_MAX, true, ['edge']), 'an acknowledged row is gone');
    }

    public function testARefusedPurgeStaysOwedAndTheDrainRetriesIt(): void
    {
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())->answer('edge', 401, '{"error":"unauthorized"}');
        $delivery = new OutboxDelivery($store, self::settings(), new TagPolicy(), $http, null, static fn (): int => self::NOW, null, 0);

        $delivery->recordTags(['p_1']);
        self::assertSame(1, $delivery->flush()->failed);
        self::assertSame(1, $delivery->status()['pending']);

        $http->answer('edge', 200);
        self::assertSame(1, $delivery->drain(50, true)->delivered);
        self::assertSame(0, $delivery->status()['pending']);
    }

    /** @return iterable<string, array{int, string, ?string, string}> */
    public static function transientFailures(): iterable
    {
        yield 'rate limited' => [429, '{"error":"slow down"}', null, 'HTTP 429'];
        yield 'unavailable' => [503, '{"error":"unavailable"}', null, 'HTTP 503'];
        yield 'response timeout' => [0, '', 'owned response timeout', 'no response'];
    }

    #[DataProvider('transientFailures')]
    public function testTransientFailureRetainsMetadataAndRetriesOnlyWhenDue(
        int $status,
        string $body,
        ?string $error,
        string $reason
    ): void {
        $now = self::NOW;
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())->answer('edge', $status, $body, $error);
        $delivery = new OutboxDelivery($store, self::settings(), new TagPolicy(), $http, null,
            static function () use (&$now): int { return $now; }, null, 0);

        self::assertSame(1, $delivery->recordTags(['p_1']));
        $report = $delivery->flush();
        self::assertSame(0, $report->delivered);
        self::assertSame(1, $report->failed);
        self::assertCount(1, $store->rows);
        $row = array_values($store->rows)[0];
        self::assertSame('edge', $row['instance']);
        self::assertSame(1, $row['attempts']);
        self::assertSame($now, $row['last_error_at']);
        self::assertSame($now + 1, $row['next_attempt_at']);
        self::assertStringContainsString($reason, (string) $row['last_error']);
        self::assertContains('p_1', $row['tags']);

        self::assertSame(0, $delivery->drain()->delivered);
        self::assertCount(1, $http->requests, 'no early retry before the real backoff');
        ++$now;
        $http->answer('edge', 200);
        self::assertSame(1, $delivery->drain()->delivered);
        self::assertSame([], $store->rows);
        self::assertCount(2, $http->requests);
    }

    public function testOneNodesAcknowledgementCannotDeleteAnotherNodesPendingWork(): void
    {
        $now = self::NOW;
        $settings = SettingsResolver::resolve(['TRIDENT_INSTANCES' =>
            '{"edge-1":{"api_url":"http://edge1:9301","api_token":"t"},"edge-2":{"api_url":"http://edge2:9301","api_token":"t"}}'], []);
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())->answer('edge1', 200)->answer('edge2', 503, '{"error":"unavailable"}');
        $clock = static function () use (&$now): int { return $now; };
        $writer = new OutboxDelivery($store, $settings, new TagPolicy(), $http, null, $clock, null, 0);
        self::assertSame(2, $writer->recordTags(['p_1']));
        $report = $writer->flush();
        self::assertSame(1, $report->delivered);
        self::assertSame(1, $report->failed);
        self::assertCount(1, $store->rows);
        self::assertSame('edge-2', array_values($store->rows)[0]['instance']);

        ++$now;
        $http->answer('edge2', 200);
        $nextProcess = new OutboxDelivery($store, $settings, new TagPolicy(), $http, null, $clock, null, 0);
        self::assertSame(1, $nextProcess->drain()->delivered);
        self::assertSame([], $store->rows);
        self::assertCount(1, $http->purgedTags('edge1'), 'already acknowledged node is not replayed');
        self::assertCount(2, $http->purgedTags('edge2'), 'only the failed node is retried');
    }

    public function testDeathAfterAcknowledgementBeforeRemoveLeavesIdempotentReplay(): void
    {
        $store = new class extends InMemoryOutboxStore {
            public bool $dieBeforeRemove = true;
            public function remove(array $ids): void
            {
                if ($this->dieBeforeRemove) {
                    throw new \RuntimeException('owned worker death after acknowledgement before deletion');
                }
                parent::remove($ids);
            }
        };
        $http = (new FakeTransport())->answer('edge', 200);
        $writer = new OutboxDelivery($store, self::settings(), new TagPolicy(), $http, null,
            static fn (): int => self::NOW, null, 0);
        $writer->recordTags(['p_1']);
        $writer->flush();
        self::assertCount(1, $store->rows, 'acknowledged intent remains durable before deletion');
        self::assertCount(1, $http->purgedTags('edge'));
        $ids = array_keys($store->rows);

        $store->dieBeforeRemove = false;
        $nextProcess = new OutboxDelivery($store, self::settings(), new TagPolicy(), $http, null,
            static fn (): int => self::NOW + 120, null, 0);
        self::assertSame($ids, array_keys($store->rows), 'a new process cannot drop work while reconstructing');
        self::assertSame(1, $nextProcess->drain()->delivered);
        self::assertSame([], $store->rows);
        self::assertCount(2, $http->purgedTags('edge'));
        self::assertSame($http->purgedTags('edge')[0], $http->purgedTags('edge')[1], 'duplicate delivery preserves the tags');
    }

    public function testWhenTheOutboxCannotBeWrittenThePurgeIsSentDirectly(): void
    {
        $store = new InMemoryOutboxStore();
        $http = (new FakeTransport())->answer('edge', 200);
        $failing = static function (callable $work): never {
            throw new \RuntimeException('database gone');
        };
        $delivery = new OutboxDelivery($store, self::settings(), new TagPolicy(), $http, $failing, static fn (): int => self::NOW, null, 0);

        self::assertSame(0, $delivery->recordTags(['p_7']));
        self::assertContains('p_7', array_merge(...$http->purgedTags('edge')), 'sent at once rather than lost');
    }

    public function testNothingIsRecordedWithoutInstances(): void
    {
        $delivery = new OutboxDelivery(new InMemoryOutboxStore(), SettingsResolver::resolve([], []), new TagPolicy(), new FakeTransport());
        self::assertFalse($delivery->settings()->enabled());
        self::assertSame(0, $delivery->recordTags(['p_1']));
    }

    public function testTheHeaderAndThePurgeAgreeOnTagNames(): void
    {
        $policy = new TagPolicy('s1_');
        $header = explode(',', $policy->headerValue(['P_1', 'c 2']));
        foreach ($policy->purgeTags(['P_1', 'c 2']) as $tag) {
            if ($tag !== 's1_' . TagPolicy::OVERFLOW) {
                self::assertContains($tag, $header);
            }
        }
        self::assertContains($policy->allTag(), $header, 'every page carries the shop-wide tag');
    }

    public function testAnEnvTokenIsNeverGivenToTheAdminUrl(): void
    {
        $s = SettingsResolver::resolve(['TRIDENT_API_TOKEN' => 'env-secret'], ['api_url' => 'http://attacker.example:9301', 'api_token' => null]);
        foreach ($s->instances as $instance) {
            self::assertNotSame('env-secret', $instance->apiToken);
        }
        self::assertInstanceOf(Instance::class, $s->instances[0] ?? new Instance('x', 'http://x', ''));
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private static function sorted(array $tags): array
    {
        sort($tags);

        return $tags;
    }
}
