<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Client;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Exception\TridentException;

class TridentClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private RequestFactoryInterface&MockObject $requestFactory;
    private StreamFactoryInterface&MockObject $streamFactory;
    private LoggerInterface&MockObject $logger;
    private RequestInterface&MockObject $request;
    private ResponseInterface&MockObject $response;
    private StreamInterface&MockObject $stream;
    private TridentClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->stream = $this->createMock(StreamInterface::class);

        $this->client = new TridentClient(
            'http://localhost:9100',
            'test-api-key',
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $this->logger
        );
    }

    private function setupSuccessfulRequest(string $method, string $expectedUrl, array $responseData, int $statusCode = 200): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with($method, $expectedUrl)
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->expects($this->once())
            ->method('__toString')
            ->willReturn(json_encode($responseData));
    }

    private function setupPostRequest(string $expectedUrl, array $responseData, int $statusCode = 200): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedUrl)
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->request->expects($this->once())
            ->method('withBody')
            ->willReturnSelf();

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->willReturn($this->stream);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->method('__toString')
            ->willReturn(json_encode($responseData));
    }

    private function setupPostRequestWithoutBody(string $expectedUrl, array $responseData, int $statusCode = 200): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $expectedUrl)
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($this->response);

        $this->response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->method('__toString')
            ->willReturn(json_encode($responseData));
    }

    public function testHealth(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/health', [
            'status' => 'healthy',
            'version' => '1.0.0',
        ]);

        $response = $this->client->health();

        $this->assertTrue($response->isHealthy());
        $this->assertEquals('healthy', $response->status);
    }

    public function testStats(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/stats', [
            'entries' => 1000,
            'memory_used' => 50000000,
            'max_memory' => 100000000,
            'evictions' => 10,
        ]);

        $response = $this->client->stats();

        $this->assertEquals(1000, $response->entries);
        $this->assertEquals(50000000, $response->memoryUsed);
        $this->assertEquals(100000000, $response->maxMemory);
    }

    public function testTopUrls(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/stats/top?limit=20&sort=requests', [
            'window_secs' => 3600,
            'tracked_urls' => 100,
            'sort' => 'requests',
            'urls' => [],
        ]);

        $response = $this->client->topUrls(20, 'requests');

        $this->assertEquals(3600, $response->windowSecs);
        $this->assertEquals('requests', $response->sort);
    }

    public function testTagStats(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/tags/stats', [
            'total_tags' => 50,
            'tags' => [
                ['tag' => 'product.1', 'entries' => 10],
            ],
        ]);

        $response = $this->client->tagStats();

        $this->assertEquals(50, $response->totalTags);
        $this->assertCount(1, $response->tags);
    }

    public function testRefreshQueue(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/refresh/queue', [
            'pending' => 5,
            'queue_capacity' => 1000,
            'workers' => 4,
            'stats' => [
                'queued' => 100,
                'completed' => 95,
                'failed' => 5,
            ],
        ]);

        $response = $this->client->refreshQueue();

        $this->assertEquals(5, $response->pending);
        $this->assertEquals(1000, $response->queueCapacity);
    }

    public function testCacheEntry(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/cache/entry?url=%2Fproducts%2F1&method=GET', [
            'found' => true,
            'status' => 'fresh',
            'ttl_remaining' => 300,
            'tags' => ['product.1'],
        ]);

        $response = $this->client->cacheEntry('/products/1');

        $this->assertTrue($response->found);
        $this->assertTrue($response->isFresh());
    }

    public function testCacheEntryWithHost(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/cache/entry?url=%2Fproducts%2F1&method=GET&host=example.com', [
            'found' => true,
            'status' => 'fresh',
        ]);

        $response = $this->client->cacheEntry('/products/1', 'example.com');

        $this->assertTrue($response->found);
    }

    public function testBackends(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/backends', [
            'total' => 2,
            'healthy' => 2,
            'unhealthy' => 0,
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
            ],
        ]);

        $response = $this->client->backends();

        $this->assertEquals(2, $response->total);
        $this->assertTrue($response->isAllHealthy());
    }

    public function testBans(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/bans?limit=100', [
            'total' => 5,
            'active' => 3,
            'bans' => [],
        ]);

        $response = $this->client->bans(100);

        $this->assertEquals(5, $response->total);
        $this->assertEquals(3, $response->active);
    }

    public function testPurgeUrl(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/url', [
            'success' => true,
            'purged' => 1,
        ]);

        $response = $this->client->purgeUrl('/products/1');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeUrls(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/urls', [
            'success' => true,
            'purged' => 3,
        ]);

        $response = $this->client->purgeUrls(['/products/1', '/products/2', '/products/3']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTag(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/tag', [
            'success' => true,
            'purged' => 10,
        ]);

        $response = $this->client->purgeTag('product.1');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTags(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/tags', [
            'success' => true,
            'purged' => 25,
        ]);

        $response = $this->client->purgeTags(['product.1', 'product.2']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTagsAll(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/tags', [
            'success' => true,
            'purged' => 5,
        ]);

        $response = $this->client->purgeTagsAll(['category.shoes', 'store.default']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeTagPattern(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/tag/pattern', [
            'success' => true,
            'purged' => 50,
        ]);

        $response = $this->client->purgeTagPattern('product.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAll(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/cache/clear', [
            'success' => true,
            'purged' => 1000,
        ]);

        $response = $this->client->purgeAll();

        $this->assertTrue($response->isSuccess());
    }

    public function testBan(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/url', [
            'success' => true,
            'purged' => 100,
        ]);

        $response = $this->client->ban('^/admin/.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testHttpErrorThrowsException(): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->response);

        $this->response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(500);

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->expects($this->once())
            ->method('__toString')
            ->willReturn('Internal Server Error');

        $this->expectException(TridentException::class);
        $this->expectExceptionMessage('Trident API error (HTTP 500)');

        $this->client->health();
    }

    public function testConnectionErrorThrowsException(): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new \Exception('Connection refused'));

        $this->expectException(TridentException::class);
        $this->expectExceptionMessage('Failed to connect to Trident');

        $this->client->health();
    }

    public function testClientWithoutApiKey(): void
    {
        $client = new TridentClient(
            'http://localhost:9100',
            null,
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $this->logger
        );

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'http://localhost:9100/admin/health')
            ->willReturn($this->request);

        // When no API key, withHeader should only be called for Content-Type (not Authorization)
        $this->request->expects($this->never())
            ->method('withHeader')
            ->with('Authorization', $this->anything());

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->response);

        $this->response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->response->expects($this->once())
            ->method('getBody')
            ->willReturn($this->stream);

        $this->stream->expects($this->once())
            ->method('__toString')
            ->willReturn('{"status": "healthy"}');

        $response = $client->health();

        $this->assertTrue($response->isHealthy());
    }

    public function testBaseUrlTrailingSlashIsRemoved(): void
    {
        $client = new TridentClient(
            'http://localhost:9100/',
            null,
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'http://localhost:9100/admin/health')
            ->willReturn($this->request);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->response);

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('getBody')->willReturn($this->stream);
        $this->stream->method('__toString')->willReturn('{"status": "healthy"}');

        $client->health();
    }

    public function testReady(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/ready', [
            'ready' => true,
            'status' => 'ready',
            'backends' => [
                'origin' => ['healthy' => true],
            ],
        ]);

        $response = $this->client->ready();

        $this->assertTrue($response->isReady());
        $this->assertEquals('ready', $response->status);
    }

    public function testDrainBackend(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/backends/drain', [
            'success' => true,
            'backend' => 'origin',
            'action' => 'drain',
        ]);

        $response = $this->client->drainBackend('origin');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('origin', $response->backend);
        $this->assertEquals('drain', $response->action);
    }

    public function testRestoreBackend(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/backends/restore', [
            'success' => true,
            'backend' => 'api',
            'action' => 'restore',
        ]);

        $response = $this->client->restoreBackend('api');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('api', $response->backend);
        $this->assertEquals('restore', $response->action);
    }

    public function testCreateBan(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/bans', [
            'success' => true,
            'id' => 123,
            'pattern' => '^/admin/.*',
        ]);

        $response = $this->client->createBan('^/admin/.*');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('123', $response->id);
        $this->assertEquals('^/admin/.*', $response->pattern);
    }

    public function testCreateBanWithType(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/bans', [
            'success' => true,
            'id' => 456,
            'pattern' => '/api/.*',
        ]);

        $response = $this->client->createBan('/api/.*', 'pattern');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('456', $response->id);
    }

    public function testDeleteBan(): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://localhost:9100/admin/bans/ban-123')
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->response);

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('getBody')->willReturn($this->stream);
        $this->stream->method('__toString')->willReturn('{"success": true}');

        $result = $this->client->deleteBan('ban-123');

        $this->assertTrue($result);
    }

    public function testReload(): void
    {
        $this->setupPostRequestWithoutBody('http://localhost:9100/admin/config/reload', [
            'success' => true,
            'status' => 'reloaded',
            'message' => 'Configuration reloaded',
        ]);

        $response = $this->client->reload();

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('reloaded', $response->status);
    }

    public function testConfig(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/config', [
            'server' => [
                'listen' => ':8080',
            ],
            'cache' => [
                'default_ttl' => '1h',
            ],
        ]);

        $response = $this->client->config();

        $this->assertEquals(':8080', $response->get('server.listen'));
        $this->assertEquals('1h', $response->get('cache.default_ttl'));
    }

    public function testLaunchStart(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/launch/start', [
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'urls_total' => 100,
            'maintenance_active' => true,
        ]);

        $response = $this->client->launchStart([
            'name' => 'v2.0 Release',
            'sitemap_url' => 'https://example.com/sitemap.xml',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('launch-123', $response->launchId);
        $this->assertTrue($response->isWarming());
        $this->assertTrue($response->isMaintenanceActive());
    }

    public function testLaunchStatus(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/launch/status/launch-123', [
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'progress' => [
                'total' => 100,
                'completed' => 50,
                'failed' => 2,
                'pending' => 48,
                'percent' => 50,
            ],
            'current_url' => 'https://example.com/page-50',
            'maintenance_active' => true,
            'can_complete' => false,
            'can_abort' => true,
        ]);

        $response = $this->client->launchStatus('launch-123');

        $this->assertEquals('launch-123', $response->launchId);
        $this->assertTrue($response->isWarming());
        $this->assertEquals(50, $response->getProgressPercent());
        $this->assertEquals(50, $response->getCompletedCount());
        $this->assertEquals(2, $response->getFailedCount());
    }

    public function testLaunchComplete(): void
    {
        $this->setupPostRequestWithoutBody('http://localhost:9100/admin/launch/complete/launch-123', [
            'launch_id' => 'launch-123',
            'status' => 'completed',
            'message' => 'Launch completed successfully',
        ]);

        $response = $this->client->launchComplete('launch-123');

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isCompleted());
    }

    public function testLaunchAbort(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/launch/abort/launch-123', [
            'launch_id' => 'launch-123',
            'status' => 'aborted',
            'reason' => 'User requested abort',
        ]);

        $response = $this->client->launchAbort('launch-123', 'User requested abort');

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isAborted());
        $this->assertEquals('User requested abort', $response->reason);
    }

    public function testLaunchAbortWithoutReason(): void
    {
        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', 'http://localhost:9100/admin/launch/abort/launch-456')
            ->willReturn($this->request);

        $this->request->expects($this->atLeastOnce())
            ->method('withHeader')
            ->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->response);

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('getBody')->willReturn($this->stream);
        $this->stream->method('__toString')->willReturn('{"launch_id": "launch-456", "status": "aborted"}');

        $response = $this->client->launchAbort('launch-456');

        $this->assertTrue($response->isAborted());
    }

    // ========================================
    // Stats Endpoints Tests
    // ========================================

    public function testLatencyStats(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/stats/latency', [
            'latency' => [
                'avg_ms' => 45.5,
                'p50_ms' => 42.0,
                'p95_ms' => 100.0,
                'p99_ms' => 150.0,
            ],
            'sample_count' => 10000,
            'window_secs' => 300,
        ]);

        $response = $this->client->latencyStats();

        $this->assertEquals(45.5, $response->getAvgMs());
        $this->assertEquals(42.0, $response->getP50Ms());
        $this->assertEquals(100.0, $response->getP95Ms());
        $this->assertEquals(10000, $response->sampleCount);
    }

    public function testErrorStats(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/stats/errors?limit=10', [
            'total_requests' => 10000,
            'total_errors' => 50,
            'error_rate' => 0.005,
            'by_status' => [500 => 30, 502 => 20],
            'recent' => [],
        ]);

        $response = $this->client->errorStats();

        $this->assertEquals(10000, $response->totalRequests);
        $this->assertEquals(50, $response->totalErrors);
        $this->assertTrue($response->hasErrors());
        $this->assertEquals(30, $response->getCountForStatus(500));
    }

    public function testErrorStatsWithLimit(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/stats/errors?limit=5', [
            'total_requests' => 5000,
            'total_errors' => 10,
            'error_rate' => 0.002,
            'by_status' => [],
            'recent' => [],
        ]);

        $response = $this->client->errorStats(5);

        $this->assertEquals(5000, $response->totalRequests);
    }

    // ========================================
    // Backend Detail Test
    // ========================================

    public function testBackendDetail(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/backends/detail?name=origin', [
            'name' => 'origin',
            'healthy' => true,
            'host' => 'localhost',
            'port' => 3000,
            'address' => '127.0.0.1:3000',
            'status' => 'healthy',
            'weight' => 100,
            'max_connections' => 1000,
            'tls' => false,
            'stats' => [
                'total_requests' => 50000,
                'failed_requests' => 10,
                'error_rate' => 0.0002,
            ],
            'latency' => [
                'avg_ms' => 45.5,
                'p50_ms' => 30.0,
                'p95_ms' => 100.0,
            ],
        ]);

        $response = $this->client->backendDetail('origin');

        $this->assertEquals('origin', $response->name);
        $this->assertTrue($response->isHealthy());
        $this->assertEquals('localhost', $response->host);
        $this->assertEquals(3000, $response->port);
        $this->assertEquals(50000, $response->getRequestCount());
        $this->assertEquals(45.5, $response->getAvgLatencyMs());
        $this->assertEquals('http://localhost:3000', $response->getUrl());
    }

    // ========================================
    // Rules Tests
    // ========================================

    public function testRules(): void
    {
        $this->setupSuccessfulRequest('GET', 'http://localhost:9100/admin/rules', [
            'request_rules' => 3,
            'response_rules' => 2,
            'request' => [
                ['name' => 'rule1', 'priority' => 100, 'enabled' => true, 'evaluations' => 1000, 'matches' => 500],
            ],
            'response' => [],
        ]);

        $response = $this->client->rules();

        $this->assertEquals(3, $response->requestRules);
        $this->assertEquals(2, $response->responseRules);
        $this->assertEquals(5, $response->getTotalRules());
    }

    public function testValidateRules(): void
    {
        $this->setupPostRequestWithoutBody('http://localhost:9100/admin/rules/validate', [
            'valid' => true,
            'request_rules' => 5,
            'response_rules' => 3,
        ]);

        $response = $this->client->validateRules();

        $this->assertTrue($response->isValid());
        $this->assertEquals(8, $response->getTotalRules());
    }

    public function testValidateRulesWithError(): void
    {
        $this->setupPostRequestWithoutBody('http://localhost:9100/admin/rules/validate', [
            'valid' => false,
            'request_rules' => 0,
            'response_rules' => 0,
            'error' => 'Invalid TOML syntax',
        ]);

        $response = $this->client->validateRules();

        $this->assertFalse($response->isValid());
        $this->assertTrue($response->hasError());
        $this->assertEquals('Invalid TOML syntax', $response->error);
    }

    // ========================================
    // Purge Hash Test
    // ========================================

    public function testPurgeHash(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/hash', [
            'success' => true,
            'purged' => 1,
        ]);

        $response = $this->client->purgeHash('0123456789abcdef');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(1, $response->getPurgedCount());
    }

    public function testPurgeHashSoft(): void
    {
        $this->setupPostRequest('http://localhost:9100/admin/purge/hash', [
            'success' => true,
            'purged' => 1,
            'queued_refresh' => 1,
        ]);

        $response = $this->client->purgeHash('0123456789abcdef', true);

        $this->assertTrue($response->isSuccess());
    }
}
