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
use Qoliber\ServerMock\Mock;
use Qoliber\ServerMock\MockServer;
use Qoliber\Trident\TridentFactory;
use Qoliber\Trident\TridentManager;

/**
 * Integration tests using ServerMock - no actual Trident required
 *
 * @group integration
 * @group servermock
 */
class ServerMockIntegrationTest extends TestCase
{
    private MockServer $server;
    private TridentManager $trident;

    protected function setUp(): void
    {
        $this->server = MockServer::start();
        $this->trident = TridentFactory::create(
            $this->server->uri(),
            'test-api-key'
        );
    }

    protected function tearDown(): void
    {
        $this->server->shutdown();
    }

    // ========================================
    // Health & Status
    // ========================================

    public function testHealth(): void
    {
        Mock::get('/admin/health')
            ->respondJson(['status' => 'healthy', 'uptime_secs' => 3600])
            ->mount($this->server);

        $response = $this->trident->health();

        $this->assertTrue($response->isHealthy());
        $this->assertEquals('healthy', $response->status);
    }

    public function testHealthUnhealthy(): void
    {
        Mock::get('/admin/health')
            ->respondJson(['status' => 'unhealthy', 'uptime_secs' => 100])
            ->mount($this->server);

        $response = $this->trident->health();

        $this->assertFalse($response->isHealthy());
    }

    public function testReady(): void
    {
        Mock::get('/admin/ready')
            ->respondJson([
                'status' => 'ready',
                'backends' => [
                    ['name' => 'origin', 'healthy' => true],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->ready();

        $this->assertTrue($response->isReady());
        $this->assertEquals('ready', $response->status);
    }

    public function testStats(): void
    {
        Mock::get('/admin/stats')
            ->respondJson([
                'entries' => 1500,
                'memory_used' => 52428800,
                'max_memory' => 104857600,
                'evictions' => 25,
                'evicted_bytes' => 1048576,
                'memory_usage_percent' => 50.0,
                'compressed_entries' => 1200,
                'compression_ratio' => 2.5,
                'compression_bytes_saved' => 26214400,
            ])
            ->mount($this->server);

        $response = $this->trident->stats();

        $this->assertEquals(1500, $response->entries);
        $this->assertEquals(52428800, $response->memoryUsed);
        $this->assertEquals(50.0, $response->memoryUsagePercent);
    }

    public function testTopUrls(): void
    {
        Mock::get('/admin/stats/top')
            ->withQueryParam('limit', '10')
            ->withQueryParam('sort', 'requests')
            ->respondJson([
                'window_secs' => 3600,
                'tracked_urls' => 100,
                'sort' => 'requests',
                'urls' => [
                    ['url' => '/products/1', 'requests' => 500, 'bytes' => 250000],
                    ['url' => '/categories/2', 'requests' => 300, 'bytes' => 150000],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->topUrls(10, 'requests');

        $this->assertEquals(3600, $response->windowSecs);
        $this->assertEquals('requests', $response->sort);
        $this->assertCount(2, $response->urls);
    }

    public function testTagStats(): void
    {
        Mock::get('/admin/tags/stats')
            ->respondJson([
                'total_tags' => 150,
                'tags' => [
                    ['tag' => 'product.1', 'entries' => 25],
                    ['tag' => 'category.5', 'entries' => 50],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->tagStats();

        $this->assertEquals(150, $response->totalTags);
        $this->assertCount(2, $response->tags);
    }

    public function testRefreshQueue(): void
    {
        Mock::get('/admin/refresh/queue')
            ->respondJson([
                'pending' => 10,
                'processing' => 2,
                'queue_capacity' => 1000,
                'workers' => 4,
            ])
            ->mount($this->server);

        $response = $this->trident->refreshQueue();

        $this->assertEquals(10, $response->pending);
        $this->assertEquals(4, $response->workers);
    }

    public function testCacheEntry(): void
    {
        Mock::get('/admin/cache/entry')
            ->withQueryParam('url', '/products/1')
            ->withQueryParam('method', 'GET')
            ->respondJson([
                'found' => true,
                'storage_key' => 'abc123hash',
                'status' => 'fresh',
                'ttl_remaining' => 3600,
                'age' => 600,
                'tags' => ['product.1', 'category.5'],
                'hits' => 100,
            ])
            ->mount($this->server);

        $response = $this->trident->cacheEntry('/products/1');

        $this->assertTrue($response->found);
        $this->assertEquals('abc123hash', $response->storageKey);
        $this->assertEquals('fresh', $response->status);
    }

    public function testCacheEntryNotFound(): void
    {
        Mock::get('/admin/cache/entry')
            ->respondJson(['found' => false])
            ->mount($this->server);

        $response = $this->trident->cacheEntry('/nonexistent');

        $this->assertFalse($response->found);
    }

    public function testLatencyStats(): void
    {
        Mock::get('/admin/stats/latency')
            ->respondJson([
                'latency' => [
                    'p50_ms' => 15.5,
                    'p75_ms' => 25.0,
                    'p90_ms' => 45.0,
                    'p95_ms' => 75.0,
                    'p99_ms' => 150.0,
                    'avg_ms' => 22.5,
                    'max_ms' => 500.0,
                ],
                'sample_count' => 10000,
            ])
            ->mount($this->server);

        $response = $this->trident->latencyStats();

        $this->assertEquals(15.5, $response->getP50Ms());
        $this->assertEquals(75.0, $response->getP95Ms());
    }

    public function testErrorStats(): void
    {
        Mock::get('/admin/stats/errors')
            ->respondJson([
                'total_errors' => 50,
                'total_requests' => 10000,
                'error_rate' => 0.005,
                'by_status' => [500 => 30, 502 => 20],
                'recent' => [],
            ])
            ->mount($this->server);

        $response = $this->trident->errorStats();

        $this->assertEquals(50, $response->totalErrors);
        $this->assertTrue($response->hasErrors());
    }

    // ========================================
    // Backends
    // ========================================

    public function testBackends(): void
    {
        Mock::get('/admin/backends')
            ->respondJson([
                'total' => 2,
                'backends' => [
                    ['name' => 'api', 'healthy' => true, 'requests' => 1000],
                    ['name' => 'static', 'healthy' => true, 'requests' => 500],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->backends();

        $this->assertEquals(2, $response->total);
        $this->assertCount(2, $response->backends);
    }

    public function testDrainBackend(): void
    {
        Mock::post('/admin/backends/drain')
            ->respondJson(['success' => true, 'message' => 'Backend drained'])
            ->mount($this->server);

        $response = $this->trident->drainBackend('api');

        $this->assertTrue($response->isSuccess());
    }

    public function testRestoreBackend(): void
    {
        Mock::post('/admin/backends/restore')
            ->respondJson(['success' => true, 'message' => 'Backend restored'])
            ->mount($this->server);

        $response = $this->trident->restoreBackend('api');

        $this->assertTrue($response->isSuccess());
    }

    public function testBackendDetail(): void
    {
        Mock::get('/admin/backends/detail')
            ->withQueryParam('name', 'api')
            ->respondJson([
                'name' => 'api',
                'healthy' => true,
                'requests' => 5000,
                'errors' => 10,
                'active_connections' => 25,
                'avg_response_ms' => 15.5,
            ])
            ->mount($this->server);

        $response = $this->trident->backendDetail('api');

        $this->assertEquals('api', $response->name);
        $this->assertTrue($response->healthy);
    }

    // ========================================
    // Banning
    // ========================================

    public function testBans(): void
    {
        Mock::get('/admin/bans')
            ->respondJson([
                'total' => 5,
                'active' => 3,
                'bans' => [
                    ['id' => 'ban-1', 'pattern' => '/admin/*', 'type' => 'pattern'],
                    ['id' => 'ban-2', 'pattern' => 'product.1', 'type' => 'tag'],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->bans();

        $this->assertEquals(5, $response->total);
        $this->assertEquals(3, $response->active);
        $this->assertCount(2, $response->bans);
    }

    public function testCreateBan(): void
    {
        Mock::post('/admin/bans')
            ->respondJson([
                'success' => true,
                'id' => 'ban-123',
                'pattern' => '^/test/.*',
                'type' => 'pattern',
            ])
            ->mount($this->server);

        $response = $this->trident->createBan('^/test/.*', 'pattern');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ban-123', $response->id);
    }

    public function testDeleteBan(): void
    {
        Mock::delete('/admin/bans/ban-123')
            ->respondJson(['success' => true])
            ->mount($this->server);

        $result = $this->trident->deleteBan('ban-123');

        $this->assertTrue($result);
    }

    // ========================================
    // Configuration
    // ========================================

    public function testReload(): void
    {
        Mock::post('/admin/config/reload')
            ->respondJson(['success' => true, 'message' => 'Configuration reloaded'])
            ->mount($this->server);

        $response = $this->trident->reload();

        $this->assertTrue($response->isSuccess());
    }

    public function testConfig(): void
    {
        Mock::get('/admin/config')
            ->respondJson([
                'listen' => '0.0.0.0:8080',
                'admin_listen' => '0.0.0.0:9100',
            ])
            ->mount($this->server);

        $response = $this->trident->config();

        $this->assertIsArray($response->config);
        $this->assertArrayHasKey('listen', $response->config);
    }

    public function testRules(): void
    {
        Mock::get('/admin/rules')
            ->respondJson([
                'request_rules' => 2,
                'response_rules' => 1,
                'request' => [
                    ['name' => 'static-assets', 'matches' => 1000, 'evaluations' => 5000],
                    ['name' => 'api-cache', 'matches' => 500, 'evaluations' => 3000],
                ],
                'response' => [],
            ])
            ->mount($this->server);

        $response = $this->trident->rules();

        $this->assertEquals(3, $response->getTotalRules());
    }

    public function testValidateRules(): void
    {
        Mock::post('/admin/rules/validate')
            ->respondJson(['valid' => true, 'errors' => []])
            ->mount($this->server);

        $response = $this->trident->validateRules();

        $this->assertTrue($response->isValid());
    }

    // ========================================
    // Purge Operations
    // ========================================

    public function testPurgeUrl(): void
    {
        Mock::post('/admin/purge/url')
            ->respondJson(['success' => true, 'purged' => 1])
            ->mount($this->server);

        $response = $this->trident->purgeUrl('http://example.com/test');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(1, $response->purgedCount);
    }

    public function testPurgeUrls(): void
    {
        Mock::post('/admin/purge/urls')
            ->respondJson(['success' => true, 'purged' => 3])
            ->mount($this->server);

        $response = $this->trident->purgeUrls([
            'http://example.com/page1',
            'http://example.com/page2',
            'http://example.com/page3',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(3, $response->purgedCount);
    }

    public function testPurgeUrlPattern(): void
    {
        Mock::post('/admin/purge/pattern')
            ->respondJson(['success' => true, 'purged' => 10])
            ->mount($this->server);

        $response = $this->trident->purgeUrlPattern('^/products/.*');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(10, $response->purgedCount);
    }

    public function testPurgeTag(): void
    {
        Mock::post('/admin/purge/tag')
            ->respondJson(['success' => true, 'purged' => 5])
            ->mount($this->server);

        $response = $this->trident->purgeTag('product.123');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(5, $response->purgedCount);
    }

    public function testPurgeTags(): void
    {
        Mock::post('/admin/purge/tags')
            ->respondJson(['success' => true, 'purged' => 15])
            ->mount($this->server);

        $response = $this->trident->purgeTags(['product.1', 'product.2', 'category.5']);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(15, $response->purgedCount);
    }

    public function testPurgeTagsAll(): void
    {
        Mock::post('/admin/purge/tags')
            ->respondJson(['success' => true, 'purged' => 2])
            ->mount($this->server);

        $response = $this->trident->purgeTagsAll(['product.1', 'featured']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTagPattern(): void
    {
        Mock::post('/admin/purge/tag/pattern')
            ->respondJson(['success' => true, 'purged' => 50])
            ->mount($this->server);

        $response = $this->trident->purgeTagPattern('product.*');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(50, $response->purgedCount);
    }

    public function testPurgeAll(): void
    {
        Mock::post('/admin/cache/clear')
            ->respondJson(['success' => true, 'purged' => 1000])
            ->mount($this->server);

        $response = $this->trident->purgeAll();

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(1000, $response->purgedCount);
    }

    public function testPurgeHash(): void
    {
        Mock::post('/admin/purge/hash')
            ->respondJson(['success' => true, 'purged' => 1])
            ->mount($this->server);

        $response = $this->trident->purgeHash('abc123def456');

        $this->assertTrue($response->isSuccess());
    }

    public function testBan(): void
    {
        Mock::post('/admin/purge/url')
            ->respondJson(['success' => true, 'purged' => 5])
            ->mount($this->server);

        $response = $this->trident->ban('^/admin/.*');

        $this->assertTrue($response->isSuccess());
    }

    // ========================================
    // New Features: Cache Browsing
    // ========================================

    public function testCacheEntries(): void
    {
        Mock::get('/admin/cache/entries')
            ->withQueryParam('limit', '50')
            ->withQueryParam('offset', '0')
            ->respondJson([
                'entries' => [
                    [
                        'key' => '/products/1',
                        'storage_key' => 'abc123',
                        'status' => 'fresh',
                        'ttl_remaining' => 3600,
                        'age' => 300,
                        'tags' => ['product.1'],
                        'hits' => 50,
                        'status_code' => 200,
                        'content_length' => 5000,
                    ],
                ],
                'total' => 100,
                'offset' => 0,
                'limit' => 50,
                'has_more' => true,
            ])
            ->mount($this->server);

        $response = $this->trident->cacheEntries(50, 0);

        $this->assertCount(1, $response->entries);
        $this->assertEquals(100, $response->total);
        $this->assertTrue($response->hasMore);
    }

    public function testCacheTags(): void
    {
        Mock::get('/admin/cache/tags')
            ->withQueryParam('limit', '100')
            ->withQueryParam('offset', '0')
            ->respondJson([
                'tags' => [
                    ['tag' => 'product.1', 'entries' => 10],
                    ['tag' => 'category.5', 'entries' => 25],
                ],
                'total' => 50,
                'offset' => 0,
                'limit' => 100,
                'has_more' => false,
            ])
            ->mount($this->server);

        $response = $this->trident->cacheTags();

        $this->assertCount(2, $response->tags);
        $this->assertEquals(50, $response->total);
        $this->assertFalse($response->hasMore);
    }

    public function testPurgePreview(): void
    {
        Mock::post('/admin/purge/preview')
            ->respondJson([
                'would_purge' => 5,
                'keys' => ['/products/1', '/products/2', '/products/3'],
                'tags' => ['product.1', 'product.2'],
                'estimated_bytes' => 25000,
            ])
            ->mount($this->server);

        $response = $this->trident->purgePreview('^/products/.*', 'url');

        $this->assertEquals(5, $response->wouldPurge);
        $this->assertCount(3, $response->keys);
        $this->assertEquals(25000, $response->estimatedBytes);
    }

    // ========================================
    // New Features: Advanced Statistics
    // ========================================

    public function testProtectionStats(): void
    {
        Mock::get('/admin/stats/protection')
            ->respondJson([
                'enabled' => true,
                'total_tripped' => 3,
                'active_trips' => 1,
                'backends' => [
                    [
                        'name' => 'api',
                        'tripped' => true,
                        'trip_count' => 2,
                        'error_count' => 100,
                        'error_rate' => 0.1,
                        'cooldown_remaining' => 30,
                    ],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->protectionStats();

        $this->assertTrue($response->enabled);
        $this->assertEquals(1, $response->activeTrips);
        $this->assertTrue($response->hasActiveTrips());
    }

    public function testMemoryStats(): void
    {
        Mock::get('/admin/stats/memory')
            ->respondJson([
                'total_bytes' => 536870912,
                'cache_body_bytes' => 400000000,
                'cache_metadata_bytes' => 50000000,
                'tag_index_bytes' => 36870912,
                'buffer_bytes' => 50000000,
                'compression_saved_bytes' => 200000000,
                'usage_percent' => 75.5,
                'max_memory' => 1073741824,
            ])
            ->mount($this->server);

        $response = $this->trident->memoryStats();

        $this->assertEquals(536870912, $response->totalBytes);
        $this->assertEquals(75.5, $response->usagePercent);
        $this->assertEquals('512 MB', $response->getTotalFormatted());
    }

    public function testConnections(): void
    {
        Mock::get('/admin/connections')
            ->respondJson([
                'total_active' => 50,
                'total_idle' => 100,
                'backends' => [
                    [
                        'name' => 'api',
                        'active' => 30,
                        'idle' => 70,
                        'max_connections' => 200,
                        'waiting_requests' => 0,
                    ],
                    [
                        'name' => 'static',
                        'active' => 20,
                        'idle' => 30,
                        'max_connections' => 100,
                        'waiting_requests' => 5,
                    ],
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->connections();

        $this->assertEquals(50, $response->totalActive);
        $this->assertEquals(100, $response->totalIdle);
        $this->assertEquals(150, $response->getTotalConnections());
        $this->assertCount(2, $response->backends);
    }

    // ========================================
    // New Features: Cache Persistence
    // ========================================

    public function testSnapshot(): void
    {
        Mock::post('/admin/cache/snapshot')
            ->respondJson([
                'success' => true,
                'path' => '/var/cache/trident/snapshot_20240115.dat',
                'entry_count' => 5000,
                'file_size' => 52428800,
                'duration_ms' => 1500,
                'compressed' => true,
            ])
            ->mount($this->server);

        $response = $this->trident->snapshot();

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(5000, $response->entryCount);
        $this->assertEquals('50 MB', $response->getFileSizeFormatted());
    }

    // ========================================
    // New Features: DNS Discovery
    // ========================================

    public function testDiscoveryList(): void
    {
        Mock::get('/admin/discovery')
            ->respondJson([
                'backends' => [
                    [
                        'name' => 'api-backend',
                        'hostname' => 'api.example.com',
                        'resolved_ips' => ['192.168.1.1', '192.168.1.2'],
                        'healthy_count' => 2,
                        'unhealthy_count' => 0,
                    ],
                ],
                'total' => 1,
            ])
            ->mount($this->server);

        $response = $this->trident->discoveryList();

        $this->assertCount(1, $response->backends);
        $this->assertEquals(1, $response->total);
    }

    public function testDiscoveryDetail(): void
    {
        Mock::get('/admin/discovery/api-backend')
            ->respondJson([
                'name' => 'api-backend',
                'hostname' => 'api.example.com',
                'port' => 443,
                'ips' => [
                    ['ip' => '192.168.1.1', 'healthy' => true, 'requests' => 1000, 'errors' => 5],
                    ['ip' => '192.168.1.2', 'healthy' => true, 'requests' => 800, 'errors' => 2],
                ],
                'refresh_interval_secs' => 60,
                'last_resolved' => '2024-01-15T10:30:00Z',
            ])
            ->mount($this->server);

        $response = $this->trident->discoveryDetail('api-backend');

        $this->assertEquals('api-backend', $response->name);
        $this->assertEquals('api.example.com', $response->hostname);
        $this->assertCount(2, $response->ips);
        $this->assertEquals(2, $response->getHealthyCount());
    }

    public function testDiscoveryRefresh(): void
    {
        Mock::post('/admin/discovery/api-backend/refresh')
            ->respondJson([
                'success' => true,
                'name' => 'api-backend',
                'ips' => ['192.168.1.1', '192.168.1.2', '192.168.1.3'],
                'message' => 'DNS refresh completed',
            ])
            ->mount($this->server);

        $response = $this->trident->discoveryRefresh('api-backend');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(3, $response->getIpCount());
    }

    // ========================================
    // Launch Mode (Enterprise)
    // ========================================

    public function testLaunchStart(): void
    {
        Mock::post('/admin/launch/start')
            ->respondJson([
                'success' => true,
                'launch_id' => 'launch-abc123',
                'status' => 'warming',
                'total_urls' => 100,
                'warmed_urls' => 0,
            ])
            ->mount($this->server);

        $response = $this->trident->launchStart([
            'name' => 'Test Launch',
            'urls' => ['http://example.com/page1', 'http://example.com/page2'],
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('launch-abc123', $response->launchId);
    }

    public function testLaunchStatus(): void
    {
        Mock::get('/admin/launch/status/launch-abc123')
            ->respondJson([
                'launch_id' => 'launch-abc123',
                'status' => 'warming',
                'progress' => [
                    'total' => 100,
                    'completed' => 50,
                    'failed' => 0,
                    'pending' => 50,
                    'percent' => 50,
                ],
            ])
            ->mount($this->server);

        $response = $this->trident->launchStatus('launch-abc123');

        $this->assertEquals('launch-abc123', $response->launchId);
        $this->assertEquals('warming', $response->status);
        $this->assertEquals(50, $response->getProgressPercent());
    }

    public function testLaunchComplete(): void
    {
        Mock::post('/admin/launch/complete/launch-abc123')
            ->respondJson([
                'success' => true,
                'launch_id' => 'launch-abc123',
                'status' => 'completed',
            ])
            ->mount($this->server);

        $response = $this->trident->launchComplete('launch-abc123');

        $this->assertTrue($response->isSuccess());
    }

    public function testLaunchAbort(): void
    {
        Mock::post('/admin/launch/abort/launch-abc123')
            ->respondJson([
                'success' => true,
                'launch_id' => 'launch-abc123',
                'status' => 'aborted',
            ])
            ->mount($this->server);

        $response = $this->trident->launchAbort('launch-abc123', 'Test cleanup');

        $this->assertTrue($response->isAborted());
    }

    // ========================================
    // Error Handling
    // ========================================

    public function testHandles404Error(): void
    {
        Mock::get('/admin/health')
            ->respondNotFound('Not Found')
            ->mount($this->server);

        $this->expectException(\Qoliber\Trident\Exception\TridentException::class);
        $this->expectExceptionCode(404);

        $this->trident->health();
    }

    public function testHandles500Error(): void
    {
        Mock::get('/admin/health')
            ->respondServerError('Internal Server Error')
            ->mount($this->server);

        $this->expectException(\Qoliber\Trident\Exception\TridentException::class);
        $this->expectExceptionCode(500);

        $this->trident->health();
    }

    public function testHandlesAuthenticationError(): void
    {
        Mock::get('/admin/health')
            ->respondJson(['error' => 'Unauthorized'], 401)
            ->mount($this->server);

        $this->expectException(\Qoliber\Trident\Exception\TridentException::class);
        $this->expectExceptionCode(401);

        $this->trident->health();
    }

    // ========================================
    // Convenience Methods
    // ========================================

    public function testPurgeProduct(): void
    {
        Mock::post('/admin/purge/tag')
            ->respondJson(['success' => true, 'purged' => 3])
            ->mount($this->server);

        $response = $this->trident->purgeProduct(123);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeCategory(): void
    {
        Mock::post('/admin/purge/tag')
            ->respondJson(['success' => true, 'purged' => 10])
            ->mount($this->server);

        $response = $this->trident->purgeCategory(45);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgePage(): void
    {
        Mock::post('/admin/purge/tag')
            ->respondJson(['success' => true, 'purged' => 1])
            ->mount($this->server);

        $response = $this->trident->purgePage('home');

        $this->assertTrue($response->isSuccess());
    }

    public function testTagCollectionPurge(): void
    {
        Mock::post('/admin/purge/tags')
            ->respondJson(['success' => true, 'purged' => 8])
            ->mount($this->server);

        $tags = $this->trident->tags()
            ->addProduct(1)
            ->addProduct(2)
            ->addCategory(5);

        $response = $this->trident->purgeCollection($tags);

        $this->assertTrue($response->isSuccess());
    }

    // ========================================
    // SSE Event Stream
    // ========================================

    public function testEventsReturnsEventStream(): void
    {
        $eventStream = $this->trident->events();

        $this->assertInstanceOf(\Qoliber\Trident\Events\EventStream::class, $eventStream);
    }
}
