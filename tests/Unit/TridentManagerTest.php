<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Cache\TagCollection;
use Qoliber\Trident\Cache\TagResolver;
use Qoliber\Trident\Client\TridentClientInterface;
use Qoliber\Trident\Response\BackendActionResponse;
use Qoliber\Trident\Response\BackendDetailResponse;
use Qoliber\Trident\Response\BackendsResponse;
use Qoliber\Trident\Response\BanCreateResponse;
use Qoliber\Trident\Response\BansResponse;
use Qoliber\Trident\Response\CacheEntryResponse;
use Qoliber\Trident\Response\CacheStatsResponse;
use Qoliber\Trident\Response\ConfigResponse;
use Qoliber\Trident\Response\ErrorStatsResponse;
use Qoliber\Trident\Response\HealthResponse;
use Qoliber\Trident\Response\LatencyStatsResponse;
use Qoliber\Trident\Response\LaunchResponse;
use Qoliber\Trident\Response\LaunchStatusResponse;
use Qoliber\Trident\Response\PurgeResponse;
use Qoliber\Trident\Response\RulesResponse;
use Qoliber\Trident\Response\RulesValidateResponse;
use Qoliber\Trident\Response\ReadyResponse;
use Qoliber\Trident\Response\RefreshQueueResponse;
use Qoliber\Trident\Response\ReloadResponse;
use Qoliber\Trident\Response\TagStatsResponse;
use Qoliber\Trident\Response\TopUrlsResponse;
use Qoliber\Trident\TridentManager;

class TridentManagerTest extends TestCase
{
    private TridentClientInterface&MockObject $client;
    private TridentManager $manager;

    protected function setUp(): void
    {
        $this->client = $this->createMock(TridentClientInterface::class);
        $this->manager = new TridentManager($this->client);
    }

    public function testGetClient(): void
    {
        $this->assertSame($this->client, $this->manager->getClient());
    }

    public function testGetTagResolver(): void
    {
        $resolver = $this->manager->getTagResolver();

        $this->assertInstanceOf(TagResolver::class, $resolver);
    }

    public function testCustomTagResolver(): void
    {
        $customResolver = new TagResolver();
        $manager = new TridentManager($this->client, $customResolver);

        $this->assertSame($customResolver, $manager->getTagResolver());
    }

    public function testHealth(): void
    {
        $healthResponse = new HealthResponse(true, 'healthy');

        $this->client->expects($this->once())
            ->method('health')
            ->willReturn($healthResponse);

        $response = $this->manager->health();

        $this->assertSame($healthResponse, $response);
        $this->assertTrue($response->isHealthy());
    }

    public function testIsHealthyWhenHealthy(): void
    {
        $healthResponse = new HealthResponse(true, 'healthy');

        $this->client->expects($this->once())
            ->method('health')
            ->willReturn($healthResponse);

        $this->assertTrue($this->manager->isHealthy());
    }

    public function testIsHealthyWhenUnhealthy(): void
    {
        $healthResponse = new HealthResponse(false, 'unhealthy');

        $this->client->expects($this->once())
            ->method('health')
            ->willReturn($healthResponse);

        $this->assertFalse($this->manager->isHealthy());
    }

    public function testIsHealthyWhenExceptionThrown(): void
    {
        $this->client->expects($this->once())
            ->method('health')
            ->willThrowException(new \Exception('Connection failed'));

        $this->assertFalse($this->manager->isHealthy());
    }

    public function testStats(): void
    {
        $statsResponse = CacheStatsResponse::fromArray([
            'entries' => 1000,
            'bytes' => 50000000,
            'hit_ratio' => 0.95,
        ]);

        $this->client->expects($this->once())
            ->method('stats')
            ->willReturn($statsResponse);

        $response = $this->manager->stats();

        $this->assertEquals(1000, $response->entries);
    }

    public function testTopUrls(): void
    {
        $topUrlsResponse = TopUrlsResponse::fromArray([
            'window_secs' => 3600,
            'tracked_urls' => 100,
            'sort' => 'requests',
            'urls' => [],
        ]);

        $this->client->expects($this->once())
            ->method('topUrls')
            ->with(50, 'bytes')
            ->willReturn($topUrlsResponse);

        $response = $this->manager->topUrls(50, 'bytes');

        $this->assertEquals(3600, $response->windowSecs);
    }

    public function testTagStats(): void
    {
        $tagStatsResponse = TagStatsResponse::fromArray([
            'total_tags' => 50,
            'tags' => [],
        ]);

        $this->client->expects($this->once())
            ->method('tagStats')
            ->willReturn($tagStatsResponse);

        $response = $this->manager->tagStats();

        $this->assertEquals(50, $response->totalTags);
    }

    public function testRefreshQueue(): void
    {
        $refreshQueueResponse = RefreshQueueResponse::fromArray([
            'pending' => 5,
            'queue_capacity' => 1000,
            'workers' => 4,
        ]);

        $this->client->expects($this->once())
            ->method('refreshQueue')
            ->willReturn($refreshQueueResponse);

        $response = $this->manager->refreshQueue();

        $this->assertEquals(5, $response->pending);
    }

    public function testCacheEntry(): void
    {
        $cacheEntryResponse = CacheEntryResponse::fromArray([
            'found' => true,
            'status' => 'fresh',
        ]);

        $this->client->expects($this->once())
            ->method('cacheEntry')
            ->with('/products/1', 'example.com', 'GET')
            ->willReturn($cacheEntryResponse);

        $response = $this->manager->cacheEntry('/products/1', 'example.com');

        $this->assertTrue($response->found);
    }

    public function testBackends(): void
    {
        $backendsResponse = BackendsResponse::fromArray([
            'total' => 2,
            'healthy' => 2,
            'unhealthy' => 0,
            'backends' => [],
        ]);

        $this->client->expects($this->once())
            ->method('backends')
            ->willReturn($backendsResponse);

        $response = $this->manager->backends();

        $this->assertEquals(2, $response->total);
    }

    public function testBackendDetail(): void
    {
        $detailResponse = BackendDetailResponse::fromArray([
            'name' => 'origin',
            'healthy' => true,
            'host' => 'localhost',
            'port' => 3000,
            'status' => 'healthy',
            'stats' => ['total_requests' => 5000, 'failed_requests' => 5],
            'latency' => ['avg_ms' => 25.0],
        ]);

        $this->client->expects($this->once())
            ->method('backendDetail')
            ->with('origin')
            ->willReturn($detailResponse);

        $response = $this->manager->backendDetail('origin');

        $this->assertEquals('origin', $response->name);
        $this->assertTrue($response->isHealthy());
        $this->assertEquals('http://localhost:3000', $response->getUrl());
    }

    public function testLatencyStats(): void
    {
        $latencyResponse = LatencyStatsResponse::fromArray([
            'latency' => [
                'p50_ms' => 10.5,
                'p75_ms' => 25.0,
                'p90_ms' => 50.0,
                'p95_ms' => 75.0,
                'p99_ms' => 100.0,
            ],
            'sample_count' => 10000,
            'window_secs' => 60,
        ]);

        $this->client->expects($this->once())
            ->method('latencyStats')
            ->willReturn($latencyResponse);

        $response = $this->manager->latencyStats();

        $this->assertEquals(10.5, $response->getP50Ms());
        $this->assertEquals(10000, $response->sampleCount);
    }

    public function testErrorStats(): void
    {
        $errorResponse = ErrorStatsResponse::fromArray([
            'total_requests' => 10000,
            'total_errors' => 50,
            'error_rate' => 0.005,
            'by_status' => [500 => 30, 502 => 20],
            'recent' => [],
        ]);

        $this->client->expects($this->once())
            ->method('errorStats')
            ->with(10)
            ->willReturn($errorResponse);

        $response = $this->manager->errorStats();

        $this->assertEquals(50, $response->totalErrors);
        $this->assertTrue($response->hasErrors());
    }

    public function testErrorStatsWithLimit(): void
    {
        $errorResponse = ErrorStatsResponse::fromArray([
            'total_requests' => 5000,
            'total_errors' => 25,
            'error_rate' => 0.005,
        ]);

        $this->client->expects($this->once())
            ->method('errorStats')
            ->with(50)
            ->willReturn($errorResponse);

        $response = $this->manager->errorStats(50);

        $this->assertEquals(25, $response->totalErrors);
    }

    public function testRules(): void
    {
        $rulesResponse = RulesResponse::fromArray([
            'request_rules' => 3,
            'response_rules' => 2,
            'request' => [
                ['name' => 'rule1', 'priority' => 100, 'enabled' => true, 'evaluations' => 500, 'matches' => 100],
            ],
            'response' => [],
        ]);

        $this->client->expects($this->once())
            ->method('rules')
            ->willReturn($rulesResponse);

        $response = $this->manager->rules();

        $this->assertEquals(5, $response->getTotalRules());
        $this->assertEquals(3, $response->requestRules);
    }

    public function testValidateRules(): void
    {
        $validateResponse = RulesValidateResponse::fromArray([
            'valid' => true,
            'request_rules' => 5,
            'response_rules' => 3,
        ]);

        $this->client->expects($this->once())
            ->method('validateRules')
            ->willReturn($validateResponse);

        $response = $this->manager->validateRules();

        $this->assertTrue($response->isValid());
        $this->assertFalse($response->hasError());
    }

    public function testValidateRulesWithError(): void
    {
        $validateResponse = RulesValidateResponse::fromArray([
            'valid' => false,
            'request_rules' => 0,
            'response_rules' => 0,
            'error' => 'Invalid TOML syntax',
        ]);

        $this->client->expects($this->once())
            ->method('validateRules')
            ->willReturn($validateResponse);

        $response = $this->manager->validateRules();

        $this->assertFalse($response->isValid());
        $this->assertTrue($response->hasError());
        $this->assertEquals('Invalid TOML syntax', $response->error);
    }

    public function testBans(): void
    {
        $bansResponse = BansResponse::fromArray([
            'total' => 5,
            'active' => 3,
            'bans' => [],
        ]);

        $this->client->expects($this->once())
            ->method('bans')
            ->with(50)
            ->willReturn($bansResponse);

        $response = $this->manager->bans(50);

        $this->assertEquals(5, $response->total);
    }

    public function testPurgeUrl(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 1,
        ]);

        $this->client->expects($this->once())
            ->method('purgeUrl')
            ->with('/products/1', false)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeUrl('/products/1');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeUrls(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 3,
        ]);

        $urls = ['/products/1', '/products/2', '/products/3'];

        $this->client->expects($this->once())
            ->method('purgeUrls')
            ->with($urls, true)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeUrls($urls, true);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeUrlPattern(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 50,
        ]);

        $this->client->expects($this->once())
            ->method('purgeUrlPattern')
            ->with('^/products/.*', false)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeUrlPattern('^/products/.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTag(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 10,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTag')
            ->with('product.1')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeTag('product.1');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTags(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 25,
        ]);

        $tags = ['product.1', 'product.2'];

        $this->client->expects($this->once())
            ->method('purgeTags')
            ->with($tags)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeTags($tags);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTagsAll(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 5,
        ]);

        $tags = ['category.shoes', 'store.default'];

        $this->client->expects($this->once())
            ->method('purgeTagsAll')
            ->with($tags)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeTagsAll($tags);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTagPattern(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 100,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTagPattern')
            ->with('product.*', 'wildcard')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeTagPattern('product.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAll(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 1000,
        ]);

        $this->client->expects($this->once())
            ->method('purgeAll')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeAll();

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeHash(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 1,
        ]);

        $this->client->expects($this->once())
            ->method('purgeHash')
            ->with('abc123def456', false)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeHash('abc123def456');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeHashSoft(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 1,
        ]);

        $this->client->expects($this->once())
            ->method('purgeHash')
            ->with('abc123def456', true)
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeHash('abc123def456', true);

        $this->assertTrue($response->isSuccess());
    }

    public function testBan(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 50,
        ]);

        $this->client->expects($this->once())
            ->method('ban')
            ->with('^/admin/.*')
            ->willReturn($purgeResponse);

        $response = $this->manager->ban('^/admin/.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeProduct(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 5,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTag')
            ->with('product.123')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeProduct(123);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeCategory(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 20,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTag')
            ->with('category.45')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeCategory(45);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgePage(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 1,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTag')
            ->with('page.about')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgePage('about');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAllProducts(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 500,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTagPattern')
            ->with('product.*', 'wildcard')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeAllProducts();

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAllCategories(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 100,
        ]);

        $this->client->expects($this->once())
            ->method('purgeTagPattern')
            ->with('category.*', 'wildcard')
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeAllCategories();

        $this->assertTrue($response->isSuccess());
    }

    public function testTags(): void
    {
        $tags = $this->manager->tags();

        $this->assertInstanceOf(TagCollection::class, $tags);
        $this->assertTrue($tags->isEmpty());
    }

    public function testPurgeCollection(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 15,
        ]);

        $tags = TagCollection::create()
            ->addProduct(1)
            ->addCategory(2);

        $this->client->expects($this->once())
            ->method('purgeTags')
            ->with(['product.1', 'category.2'])
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeCollection($tags);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeEntity(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 10,
        ]);

        $entity = new class {
            public int $id = 123;
        };

        $this->client->expects($this->once())
            ->method('purgeTags')
            ->with(['product.123'])
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeEntity('product', $entity);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeEntities(): void
    {
        $purgeResponse = PurgeResponse::fromArray([
            'success' => true,
            'purged' => 30,
        ]);

        $entities = [
            new class { public int $id = 1; },
            new class { public int $id = 2; },
            new class { public int $id = 3; },
        ];

        $this->client->expects($this->once())
            ->method('purgeTags')
            ->with(['product.1', 'product.2', 'product.3'])
            ->willReturn($purgeResponse);

        $response = $this->manager->purgeEntities('product', $entities);

        $this->assertTrue($response->isSuccess());
    }

    // ========================================
    // Ready Tests
    // ========================================

    public function testReady(): void
    {
        $readyResponse = new ReadyResponse(true, 'ready', []);

        $this->client->expects($this->once())
            ->method('ready')
            ->willReturn($readyResponse);

        $response = $this->manager->ready();

        $this->assertSame($readyResponse, $response);
        $this->assertTrue($response->isReady());
    }

    public function testIsReadyWhenReady(): void
    {
        $readyResponse = new ReadyResponse(true, 'ready', []);

        $this->client->expects($this->once())
            ->method('ready')
            ->willReturn($readyResponse);

        $this->assertTrue($this->manager->isReady());
    }

    public function testIsReadyWhenNotReady(): void
    {
        $readyResponse = new ReadyResponse(false, 'not_ready', []);

        $this->client->expects($this->once())
            ->method('ready')
            ->willReturn($readyResponse);

        $this->assertFalse($this->manager->isReady());
    }

    public function testIsReadyWhenExceptionThrown(): void
    {
        $this->client->expects($this->once())
            ->method('ready')
            ->willThrowException(new \Exception('Connection failed'));

        $this->assertFalse($this->manager->isReady());
    }

    // ========================================
    // Backend Management Tests
    // ========================================

    public function testDrainBackend(): void
    {
        $actionResponse = BackendActionResponse::fromArray([
            'success' => true,
            'backend' => 'origin',
            'action' => 'drain',
        ]);

        $this->client->expects($this->once())
            ->method('drainBackend')
            ->with('origin')
            ->willReturn($actionResponse);

        $response = $this->manager->drainBackend('origin');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('drain', $response->action);
    }

    public function testRestoreBackend(): void
    {
        $actionResponse = BackendActionResponse::fromArray([
            'success' => true,
            'backend' => 'origin',
            'action' => 'restore',
        ]);

        $this->client->expects($this->once())
            ->method('restoreBackend')
            ->with('origin')
            ->willReturn($actionResponse);

        $response = $this->manager->restoreBackend('origin');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('restore', $response->action);
    }

    // ========================================
    // Banning Tests
    // ========================================

    public function testCreateBan(): void
    {
        $banResponse = BanCreateResponse::fromArray([
            'success' => true,
            'id' => 'ban-123',
            'pattern' => '^/admin/.*',
        ]);

        $this->client->expects($this->once())
            ->method('createBan')
            ->with('^/admin/.*', '1h')
            ->willReturn($banResponse);

        $response = $this->manager->createBan('^/admin/.*', '1h');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ban-123', $response->id);
    }

    public function testDeleteBan(): void
    {
        $this->client->expects($this->once())
            ->method('deleteBan')
            ->with('ban-123')
            ->willReturn(true);

        $result = $this->manager->deleteBan('ban-123');

        $this->assertTrue($result);
    }

    // ========================================
    // Configuration Tests
    // ========================================

    public function testReload(): void
    {
        $reloadResponse = ReloadResponse::fromArray([
            'success' => true,
            'status' => 'reloaded',
        ]);

        $this->client->expects($this->once())
            ->method('reload')
            ->willReturn($reloadResponse);

        $response = $this->manager->reload();

        $this->assertTrue($response->isSuccess());
    }

    public function testConfig(): void
    {
        $configResponse = ConfigResponse::fromArray([
            'server' => ['listen' => ':8080'],
            'cache' => ['default_ttl' => '1h'],
        ]);

        $this->client->expects($this->once())
            ->method('config')
            ->willReturn($configResponse);

        $response = $this->manager->config();

        $this->assertEquals(':8080', $response->get('server.listen'));
    }

    // ========================================
    // Launch Mode Tests
    // ========================================

    public function testLaunchStart(): void
    {
        $launchResponse = LaunchResponse::fromArray([
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'urls_total' => 100,
            'maintenance_active' => true,
        ]);

        $options = [
            'name' => 'v2.0 Release',
            'sitemap_url' => 'https://example.com/sitemap.xml',
        ];

        $this->client->expects($this->once())
            ->method('launchStart')
            ->with($options)
            ->willReturn($launchResponse);

        $response = $this->manager->launchStart($options);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('launch-123', $response->launchId);
        $this->assertTrue($response->isWarming());
    }

    public function testLaunchStatus(): void
    {
        $statusResponse = LaunchStatusResponse::fromArray([
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'progress' => [
                'total' => 100,
                'completed' => 50,
                'failed' => 2,
                'pending' => 48,
                'percent' => 50,
            ],
        ]);

        $this->client->expects($this->once())
            ->method('launchStatus')
            ->with('launch-123')
            ->willReturn($statusResponse);

        $response = $this->manager->launchStatus('launch-123');

        $this->assertEquals('launch-123', $response->launchId);
        $this->assertEquals(50, $response->getProgressPercent());
    }

    public function testLaunchComplete(): void
    {
        $launchResponse = LaunchResponse::fromArray([
            'launch_id' => 'launch-123',
            'status' => 'completed',
        ]);

        $this->client->expects($this->once())
            ->method('launchComplete')
            ->with('launch-123')
            ->willReturn($launchResponse);

        $response = $this->manager->launchComplete('launch-123');

        $this->assertTrue($response->isCompleted());
    }

    public function testLaunchAbort(): void
    {
        $launchResponse = LaunchResponse::fromArray([
            'launch_id' => 'launch-123',
            'status' => 'aborted',
            'reason' => 'User requested abort',
        ]);

        $this->client->expects($this->once())
            ->method('launchAbort')
            ->with('launch-123', 'User requested abort')
            ->willReturn($launchResponse);

        $response = $this->manager->launchAbort('launch-123', 'User requested abort');

        $this->assertTrue($response->isAborted());
        $this->assertEquals('User requested abort', $response->reason);
    }

    public function testLaunchAbortWithoutReason(): void
    {
        $launchResponse = LaunchResponse::fromArray([
            'launch_id' => 'launch-456',
            'status' => 'aborted',
        ]);

        $this->client->expects($this->once())
            ->method('launchAbort')
            ->with('launch-456', null)
            ->willReturn($launchResponse);

        $response = $this->manager->launchAbort('launch-456');

        $this->assertTrue($response->isAborted());
    }
}
