<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Admin;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Qoliber\Trident\Admin\AdminContext;
use Qoliber\Trident\Admin\AdminException;
use Qoliber\Trident\Admin\AdminService;
use Qoliber\Trident\Admin\EntityInvalidator;
use Qoliber\Trident\Admin\PurgeOutbox;
use Qoliber\Trident\Admin\ShopAdapter;
use Qoliber\Trident\Delivery\DrainReport;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Http\NetworkGuard;
use Qoliber\Trident\Http\TransportFactory;

final class AdminServiceTest extends TestCase
{
    /** @var list<array{string, string, mixed}> */
    private array $sent = [];
    private FakeOutbox $outbox;
    private string $engineVersion = '1.8.0';

    protected function setUp(): void
    {
        $this->outbox = new FakeOutbox();
    }

    private function service(?ShopAdapter $shop = null): AdminService
    {
        $handler = function (RequestInterface $r) {
            $this->sent[] = [$r->getMethod(), $r->getUri()->getPath(), json_decode((string) $r->getBody(), true)];

            return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], match ($r->getUri()->getPath()) {
                '/admin/status' => json_encode(['version' => $this->engineVersion, 'mode' => 'licensed', 'license' => 'valid']),
                '/admin/bans' => '{"success":true,"id":1,"pattern":"x"}',
                default => '{"total_purged":0,"purged":0,"mode":"soft"}',
            }));
        };
        $transports = new TransportFactory(5.0, 2.0, new NetworkGuard(static fn (): array => ['10.0.0.5']), \Closure::fromCallable($handler), true);
        $outbox = $this->outbox;
        $context = static fn (): AdminContext => new class ($outbox) implements AdminContext {
            public function __construct(private readonly PurgeOutbox $o)
            {
            }

            public function instances(): array
            {
                return [new Instance('edge', 'http://trident:9301', 't')];
            }

            public function mode(): string
            {
                return 'soft';
            }

            public function view(): array
            {
                return ['source' => 'test'];
            }

            public function outbox(): PurgeOutbox
            {
                return $this->o;
            }
        };

        return new AdminService($context, $shop ?? new FakeShop(), $transports);
    }

    /**
     * Lockstep versioning: the dashboard says so when the connected engine is
     * on another MAJOR.MINOR than the integration — for every platform, since
     * they all render this summary.
     */
    public function testTheDashboardWarnsWhenTheEngineIsOnAnotherReleaseLine(): void
    {
        $this->engineVersion = '1.9.0';
        $page = json_encode($this->service()->screen('dashboard', []), JSON_UNESCAPED_UNICODE);
        self::assertIsString($page);
        self::assertStringContainsString('Trident 1.9.0 is connected', $page);
        self::assertStringContainsString('Upgrade this integration', $page);
    }

    public function testTheDashboardSaysOkOnTheSameReleaseLine(): void
    {
        $this->engineVersion = '1.8.3';
        $page = json_encode($this->service()->screen('dashboard', []), JSON_UNESCAPED_UNICODE);
        self::assertIsString($page);
        self::assertStringContainsString('OK — built for Trident 1.8.x', $page);
        self::assertStringNotContainsString('is connected, but', $page);
    }

    public function testAPatternPurgeIsScopedToTheShopsHosts(): void
    {
        $this->service()->action('purge_pattern', ['pattern' => '^/sale/', 'confirm' => true]);
        $purge = array_values(array_filter($this->sent, static fn ($s) => $s[1] === '/admin/purge/pattern'))[0];
        self::assertSame('^[A-Z]+:[a-z]+:(?:shop\.example|shop\.example:8443):/sale/', $purge[2]['pattern']);
    }

    public function testAnInvalidRegexIsRefusedBeforeAnyRequest(): void
    {
        try {
            $this->service()->action('purge_pattern', ['pattern' => '^/(unclosed', 'confirm' => true]);
            self::fail('accepted');
        } catch (AdminException $e) {
            self::assertStringContainsString('regular expression', $e->getMessage());
        }
        self::assertSame([], $this->sent);
    }

    public function testBansAreScopedToo(): void
    {
        $this->service()->action('ban_create', ['pattern' => '/sale/1', 'type' => 'url', 'confirm' => true]);
        $this->service()->action('ban_create', ['pattern' => '^/sale/', 'type' => 'pattern', 'confirm' => true]);
        $bans = array_values(array_filter($this->sent, static fn ($s) => $s[1] === '/admin/bans'));
        self::assertSame('https://shop.example/sale/1', $bans[0][2]['pattern']);
        self::assertStringStartsWith('^[A-Z]+:[a-z]+:(?:shop\.example', $bans[1][2]['pattern']);
        $this->expectException(AdminException::class);
        $this->service()->action('ban_create', ['pattern' => 'http://evil.example/x', 'type' => 'url', 'confirm' => true]);
    }

    public function testDestructiveActionsNeedConfirmation(): void
    {
        foreach (['purge_all', 'purge_pattern', 'ban_create', 'launch_start'] as $action) {
            try {
                $this->service()->action($action, ['pattern' => '/x']);
                self::fail($action);
            } catch (AdminException $e) {
                self::assertStringContainsString('confirm', $e->getMessage());
            }
        }
        self::assertSame([], $this->sent);
    }

    public function testEntitiesGoThroughThePlatformsInvalidatorOrTheOutbox(): void
    {
        $this->service()->action('purge_entities', ['kind' => 'product', 'ids' => ['7']]);
        self::assertSame([['p_7']], $this->outbox->recorded, 'no invalidator: the outbox');

        $shop = new class extends FakeShop implements EntityInvalidator {
            /** @var list<list<string>> */
            public array $invalidated = [];

            public function invalidate(array $tags): void
            {
                $this->invalidated[] = $tags;
            }
        };
        $this->outbox->recorded = [];
        $this->service($shop)->action('purge_products', ['ids' => ['8']]);
        self::assertSame([['p_8']], $shop->invalidated);
        self::assertSame([], $this->outbox->recorded, 'the platform records it itself');
    }

    public function testTheShopWideTagNeedsConfirmation(): void
    {
        $this->expectException(AdminException::class);
        $this->service()->action('purge_tags', ['tags' => ['ALL']]);
    }
}

class FakeShop implements ShopAdapter
{
    public function hosts(): array
    {
        return ['shop.example', 'shop.example:8443'];
    }

    public function scheme(string $host): string
    {
        return 'https';
    }

    public function catalogUrls(int $limit): array
    {
        return ['https://shop.example/'];
    }

    public function entityKinds(): array
    {
        return ['product' => 'Products'];
    }

    public function entityTags(string $kind, array $ids): array
    {
        return array_map(static fn (string $id): string => 'p_' . $id, $ids);
    }
}

final class FakeOutbox implements PurgeOutbox
{
    /** @var list<list<string>> */
    public array $recorded = [];

    public function purgeTags(iterable $tags): array
    {
        return array_map(static fn ($t): string => strtolower((string) $t), is_array($tags) ? $tags : iterator_to_array($tags));
    }

    public function allTag(): string
    {
        return 'all';
    }

    public function recordTridentTags(array $tags): int
    {
        $this->recorded[] = $tags;

        return 1;
    }

    public function recordAll(): int
    {
        return $this->recordTridentTags(['all']);
    }

    public function flush(): DrainReport
    {
        return new DrainReport();
    }

    public function drain(int $limit, bool $ignoreBackoff): DrainReport
    {
        return new DrainReport();
    }

    public function status(): array
    {
        return ['pending' => 0];
    }
}
