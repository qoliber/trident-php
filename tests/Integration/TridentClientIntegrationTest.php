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
use Qoliber\Trident\TridentFactory;
use Qoliber\Trident\TridentManager;

/**
 * Integration tests for Trident client
 *
 * These tests require a running Trident instance.
 * Run with: docker-compose up -d && composer test
 *
 * @group integration
 */
class TridentClientIntegrationTest extends TestCase
{
    private TridentManager $trident;

    protected function setUp(): void
    {
        $adminUrl = getenv('TRIDENT_ADMIN_URL') ?: 'http://trident:9100';
        $apiKey = getenv('TRIDENT_ADMIN_KEY') ?: 'test-admin-key-12345';

        $this->trident = TridentFactory::create($adminUrl, $apiKey);
    }

    public function testHealthCheck(): void
    {
        $response = $this->trident->health();

        $this->assertTrue($response->isHealthy());
        $this->assertEquals('healthy', $response->status);
    }

    public function testIsHealthy(): void
    {
        $this->assertTrue($this->trident->isHealthy());
    }

    public function testGetStats(): void
    {
        $response = $this->trident->stats();

        $this->assertGreaterThanOrEqual(0, $response->entries);
        $this->assertGreaterThanOrEqual(0, $response->bytes);
        $this->assertGreaterThanOrEqual(0, $response->hits);
        $this->assertGreaterThanOrEqual(0, $response->misses);
        $this->assertGreaterThanOrEqual(0.0, $response->hitRatio);
        $this->assertLessThanOrEqual(1.0, $response->hitRatio);
    }

    public function testPurgeByTag(): void
    {
        $response = $this->trident->purgeTag('test.tag');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeByTags(): void
    {
        $response = $this->trident->purgeTags(['test.tag1', 'test.tag2']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeByTagsAll(): void
    {
        $response = $this->trident->purgeTagsAll(['test.tag1', 'test.tag2']);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeByTagPattern(): void
    {
        $response = $this->trident->purgeTagPattern('test.*');

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeByUrl(): void
    {
        $response = $this->trident->purgeUrl('http://localhost/test-page');

        $this->assertTrue($response->isSuccess());
    }

    public function testSoftPurgeByUrl(): void
    {
        $response = $this->trident->purgeUrl('http://localhost/test-page', soft: true);

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAll(): void
    {
        $response = $this->trident->purgeAll();

        $this->assertTrue($response->isSuccess());
    }

    public function testConveniencePurgeMethods(): void
    {
        // These should all succeed without error
        $this->assertTrue($this->trident->purgeProduct(123)->isSuccess());
        $this->assertTrue($this->trident->purgeCategory(45)->isSuccess());
        $this->assertTrue($this->trident->purgePage('home')->isSuccess());
    }

    public function testPurgeAllProducts(): void
    {
        $response = $this->trident->purgeAllProducts();

        $this->assertTrue($response->isSuccess());
    }

    public function testPurgeAllCategories(): void
    {
        $response = $this->trident->purgeAllCategories();

        $this->assertTrue($response->isSuccess());
    }

    public function testTagCollectionPurge(): void
    {
        $tags = $this->trident->tags()
            ->addProduct(1)
            ->addProduct(2)
            ->addCategory(5);

        $response = $this->trident->purgeCollection($tags);

        $this->assertTrue($response->isSuccess());
    }

    // ========================================
    // Readiness Tests
    // ========================================

    public function testReady(): void
    {
        $response = $this->trident->ready();

        $this->assertTrue($response->isReady());
        $this->assertEquals('ready', $response->status);
    }

    public function testIsReady(): void
    {
        $this->assertTrue($this->trident->isReady());
    }

    // ========================================
    // Stats & Info Tests
    // ========================================

    public function testTopUrls(): void
    {
        $response = $this->trident->topUrls(10, 'requests');

        $this->assertIsInt($response->windowSecs);
        $this->assertGreaterThanOrEqual(0, $response->trackedUrls);
        $this->assertIsArray($response->urls);
    }

    public function testTopUrlsByBytes(): void
    {
        $response = $this->trident->topUrls(5, 'bytes');

        $this->assertEquals('bytes', $response->sort);
    }

    public function testTagStats(): void
    {
        $response = $this->trident->tagStats();

        $this->assertGreaterThanOrEqual(0, $response->totalTags);
        $this->assertIsArray($response->tags);
    }

    public function testRefreshQueue(): void
    {
        $response = $this->trident->refreshQueue();

        $this->assertGreaterThanOrEqual(0, $response->pending);
        $this->assertGreaterThan(0, $response->queueCapacity);
        $this->assertGreaterThan(0, $response->workers);
    }

    public function testCacheEntryNotFound(): void
    {
        $response = $this->trident->cacheEntry('/nonexistent-url-12345');

        $this->assertFalse($response->found);
    }

    // ========================================
    // Backend Tests
    // ========================================

    public function testBackends(): void
    {
        $response = $this->trident->backends();

        $this->assertGreaterThanOrEqual(1, $response->total);
        $this->assertIsArray($response->backends);
    }

    public function testDrainAndRestoreBackend(): void
    {
        // Get current backends
        $backends = $this->trident->backends();

        if (empty($backends->backends)) {
            $this->markTestSkipped('No backends configured');
        }

        // Get first backend name
        $backendName = $backends->backends[0]['name'] ?? 'origin';

        // Drain backend
        $drainResponse = $this->trident->drainBackend($backendName);
        $this->assertTrue($drainResponse->isSuccess());

        // Restore backend
        $restoreResponse = $this->trident->restoreBackend($backendName);
        $this->assertTrue($restoreResponse->isSuccess());
    }

    // ========================================
    // Banning Tests
    // ========================================

    public function testBans(): void
    {
        $response = $this->trident->bans(50);

        $this->assertGreaterThanOrEqual(0, $response->total);
        $this->assertGreaterThanOrEqual(0, $response->active);
        $this->assertIsArray($response->bans);
    }

    public function testCreateAndDeleteBan(): void
    {
        // Create a ban (second param is type: url, tag, pattern, bulkurl, tags)
        $createResponse = $this->trident->createBan('^/test-ban-pattern/.*', 'pattern');

        $this->assertTrue($createResponse->isSuccess());
        $this->assertNotNull($createResponse->id);

        // Delete the ban
        $deleteResult = $this->trident->deleteBan($createResponse->id);

        $this->assertTrue($deleteResult);
    }

    // ========================================
    // Configuration Tests
    // ========================================

    public function testReload(): void
    {
        $response = $this->trident->reload();

        $this->assertTrue($response->isSuccess());
    }

    public function testConfig(): void
    {
        $response = $this->trident->config();

        $this->assertIsArray($response->config);
        // Config should have some basic structure
        $this->assertNotEmpty($response->config);
    }

    // ========================================
    // Launch Mode Tests (Enterprise Feature)
    // ========================================

    /**
     * @group enterprise
     */
    public function testLaunchModeWorkflow(): void
    {
        try {
            // Start a launch
            $startResponse = $this->trident->launchStart([
                'name' => 'Integration Test Launch',
                'urls' => [
                    'http://localhost/page1',
                    'http://localhost/page2',
                ],
            ]);
        } catch (\Qoliber\Trident\Exception\TridentException $e) {
            $this->markTestSkipped('Launch mode not enabled: ' . $e->getMessage());
            return;
        }

        if (!$startResponse->isSuccess()) {
            $this->markTestSkipped('Launch mode not available (Enterprise feature)');
            return;
        }

        $this->assertTrue($startResponse->isSuccess());
        $this->assertNotNull($startResponse->launchId);

        $launchId = $startResponse->launchId;

        // Get launch status
        $statusResponse = $this->trident->launchStatus($launchId);
        $this->assertEquals($launchId, $statusResponse->launchId);

        // Abort the launch (cleanup)
        $abortResponse = $this->trident->launchAbort($launchId, 'Integration test cleanup');
        $this->assertTrue($abortResponse->isAborted());
    }
}
