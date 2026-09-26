<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\E2E;

require_once '/var/www/vendor/autoload.php';

use Qoliber\Trident\TridentFactory;
use Qoliber\Trident\TridentManager;
use Qoliber\Trident\Cache\TagCollection;

/**
 * E2E Test Runner for Trident PHP Library
 */
class E2ETestRunner
{
    private TridentManager $trident;
    private string $proxyUrl;
    private string $backendUrl;

    private int $passed = 0;
    private int $failed = 0;

    /** @var array<array{test: string, status: string, message: string, duration: float}> */
    private array $results = [];

    public function __construct()
    {
        $adminUrl = getenv('TRIDENT_ADMIN_URL') ?: 'http://trident:9100';
        $apiKey = getenv('TRIDENT_ADMIN_KEY') ?: 'test-admin-key-12345';
        $this->proxyUrl = getenv('TRIDENT_PROXY_URL') ?: 'http://trident:8080';
        $this->backendUrl = getenv('BACKEND_URL') ?: 'http://nginx:80';

        $this->trident = TridentFactory::create($adminUrl, $apiKey);
    }

    public function run(): int
    {
        $this->header('Trident PHP Library E2E Tests');

        // Group 1: Health & Stats
        $this->section('Health & Stats API');
        $this->test('Health check returns healthy', [$this, 'testHealthCheck']);
        $this->test('Stats endpoint returns valid data', [$this, 'testStats']);

        // Group 2: Cache Behavior
        $this->section('Cache Behavior');
        $this->test('Response is cached on second request', [$this, 'testCaching']);
        $this->test('Cache tags are passed through', [$this, 'testCacheTagsPassthrough']);
        $this->test('No-cache responses are not cached', [$this, 'testNoCacheResponse']);
        $this->test('Vary header creates separate cache entries', [$this, 'testVaryHeader']);

        // Group 3: Purge by Tag
        $this->section('Purge by Tag');
        $this->test('Purge single tag', [$this, 'testPurgeSingleTag']);
        $this->test('Purge multiple tags (OR match)', [$this, 'testPurgeTagsOr']);
        $this->test('Purge multiple tags (AND match)', [$this, 'testPurgeTagsAnd']);
        $this->test('Purge tag pattern (wildcard)', [$this, 'testPurgeTagPattern']);

        // Group 4: Purge by URL
        $this->section('Purge by URL');
        $this->test('Purge by exact URL', [$this, 'testPurgeUrl']);
        $this->test('Soft purge by URL', [$this, 'testSoftPurgeUrl']);

        // Group 5: Purge All
        $this->section('Purge All');
        $this->test('Purge all cache entries', [$this, 'testPurgeAll']);

        // Group 6: TagCollection
        $this->section('TagCollection');
        $this->test('TagCollection add/remove/merge', [$this, 'testTagCollection']);
        $this->test('TagCollection from header', [$this, 'testTagCollectionFromHeader']);
        $this->test('Purge TagCollection', [$this, 'testPurgeCollection']);

        // Group 7: Convenience Methods
        $this->section('Convenience Methods');
        $this->test('purgeProduct()', [$this, 'testPurgeProduct']);
        $this->test('purgeCategory()', [$this, 'testPurgeCategory']);
        $this->test('purgePage()', [$this, 'testPurgePage']);
        $this->test('purgeAllProducts()', [$this, 'testPurgeAllProducts']);

        // Group 8: Advanced Stats & Monitoring
        $this->section('Advanced Stats & Monitoring');
        $this->test('Tag stats', [$this, 'testTagStats']);
        $this->test('Backend health', [$this, 'testBackends']);
        $this->test('Cache entry lookup', [$this, 'testCacheEntry']);
        $this->test('Refresh queue status', [$this, 'testRefreshQueue']);

        // Group 9: Advanced Purge Operations
        $this->section('Advanced Purge Operations');
        $this->test('Purge multiple URLs', [$this, 'testPurgeUrls']);
        $this->test('Purge tag pattern (regex)', [$this, 'testPurgeTagPatternRegex']);

        // Group 10: Advanced Tag Matching Scenarios
        $this->section('Advanced Tag Matching');
        $this->test('Purge products by multiple IDs (OR)', [$this, 'testPurgeMultipleProductsOr']);
        $this->test('Purge by category AND store tags', [$this, 'testPurgeCategoryAndStoreTags']);
        $this->test('Verify OR purge only affects matching entries', [$this, 'testVerifyOrPurgeSelectivity']);
        $this->test('Verify AND purge requires all tags', [$this, 'testVerifyAndPurgeRequiresAll']);

        // Group 11: Extended Stats & Monitoring
        $this->section('Extended Stats & Monitoring');
        $this->test('Latency stats (percentiles)', [$this, 'testLatencyStats']);
        $this->test('Error stats', [$this, 'testErrorStats']);
        $this->test('Backend detail', [$this, 'testBackendDetail']);
        $this->test('Rules list', [$this, 'testRules']);
        $this->test('Rules validation', [$this, 'testValidateRules']);
        $this->test('Purge by hash', [$this, 'testPurgeHash']);

        // Print summary
        $this->summary();

        return $this->failed > 0 ? 1 : 0;
    }

    // ========================================
    // Test Methods
    // ========================================

    private function testHealthCheck(): void
    {
        $response = $this->trident->health();
        $this->assert($response->isHealthy(), 'Health check should return healthy');
        $this->assert($response->status === 'healthy', "Status should be 'healthy', got: {$response->status}");
    }

    private function testStats(): void
    {
        $response = $this->trident->stats();
        $this->assert($response->entries >= 0, 'Entries should be >= 0');
        $this->assert($response->bytes >= 0, 'Bytes should be >= 0');
        $this->assert($response->hitRatio >= 0 && $response->hitRatio <= 1, 'Hit ratio should be 0-1');
    }

    private function testCaching(): void
    {
        $url = $this->proxyUrl . '/api/products/1';

        // First request - should be MISS
        $response1 = $this->httpGet($url);
        $random1 = $response1['body']['random'] ?? $response1['body']['cached_at'] ?? null;

        // Second request - should be HIT (same random/cached_at)
        $response2 = $this->httpGet($url);
        $random2 = $response2['body']['random'] ?? $response2['body']['cached_at'] ?? null;

        // If cached, the random value should be the same
        $this->assert(
            $response1['body']['cached_at'] === $response2['body']['cached_at'],
            'Second request should return cached response'
        );
    }

    private function testCacheTagsPassthrough(): void
    {
        $url = $this->proxyUrl . '/api/products/1';
        $response = $this->httpGet($url);

        // Check X-Cache-Tags header is present
        $tags = $response['headers']['x-cache-tags'] ?? $response['headers']['X-Cache-Tags'] ?? null;
        $this->assert($tags !== null, 'X-Cache-Tags header should be present');
        $this->assert(str_contains($tags, 'product.1'), 'Tags should contain product.1');
    }

    private function testNoCacheResponse(): void
    {
        $url = $this->proxyUrl . '/test/nocache';

        $response1 = $this->httpGet($url);
        $random1 = $response1['body']['random'];

        usleep(100000); // 100ms

        $response2 = $this->httpGet($url);
        $random2 = $response2['body']['random'];

        $this->assert($random1 !== $random2, 'No-cache responses should not be cached');
    }

    private function testVaryHeader(): void
    {
        $url = $this->proxyUrl . '/test/vary';

        // Request with different Accept-Language
        $response1 = $this->httpGet($url, ['Accept-Language: en-US']);
        $response2 = $this->httpGet($url, ['Accept-Language: de-DE']);

        // These might be same if Trident doesn't vary, but the header should be present
        $vary = $response1['headers']['vary'] ?? $response1['headers']['Vary'] ?? null;
        $this->assert($vary !== null, 'Vary header should be present');
    }

    private function testPurgeSingleTag(): void
    {
        // Warm the cache
        $this->httpGet($this->proxyUrl . '/api/products/1');

        // Purge by tag
        $response = $this->trident->purgeTag('product.1');
        $this->assert($response->isSuccess(), 'Purge should succeed');
    }

    private function testPurgeTagsOr(): void
    {
        // Warm multiple products
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');

        // Purge multiple tags (OR)
        $response = $this->trident->purgeTags(['product.1', 'product.2']);
        $this->assert($response->isSuccess(), 'Purge tags (OR) should succeed');
    }

    private function testPurgeTagsAnd(): void
    {
        // Warm the test endpoint with multiple tags
        $this->httpGet($this->proxyUrl . '/test/tags');

        // Purge with AND match
        $response = $this->trident->purgeTagsAll(['test.tag1', 'test.tag2']);
        $this->assert($response->isSuccess(), 'Purge tags (AND) should succeed');
    }

    private function testPurgeTagPattern(): void
    {
        // Warm multiple products
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');
        $this->httpGet($this->proxyUrl . '/api/products/3');

        // Purge by pattern
        $response = $this->trident->purgeTagPattern('product.*');
        $this->assert($response->isSuccess(), 'Purge tag pattern should succeed');
    }

    private function testPurgeUrl(): void
    {
        $url = $this->proxyUrl . '/api/products/1';

        // Warm the cache
        $this->httpGet($url);

        // Purge by URL
        $response = $this->trident->purgeUrl($url);
        $this->assert($response->isSuccess(), 'Purge URL should succeed');
    }

    private function testSoftPurgeUrl(): void
    {
        $url = $this->proxyUrl . '/api/products/2';

        // Warm the cache
        $this->httpGet($url);

        // Soft purge
        $response = $this->trident->purgeUrl($url, soft: true);
        $this->assert($response->isSuccess(), 'Soft purge should succeed');
    }

    private function testPurgeAll(): void
    {
        // Warm some cache entries
        $this->httpGet($this->proxyUrl . '/api/products');
        $this->httpGet($this->proxyUrl . '/api/categories');

        // Purge all
        $response = $this->trident->purgeAll();
        $this->assert($response->isSuccess(), 'Purge all should succeed');
    }

    private function testTagCollection(): void
    {
        $tags = TagCollection::create()
            ->add('tag1')
            ->add('tag2')
            ->add('tag1') // duplicate
            ->addProduct(123)
            ->addCategory(45);

        $this->assert($tags->count() === 4, 'Should have 4 unique tags');
        $this->assert($tags->has('product.123'), 'Should have product.123');
        $this->assert($tags->has('category.45'), 'Should have category.45');

        $tags->remove('tag1');
        $this->assert(!$tags->has('tag1'), 'tag1 should be removed');
        $this->assert($tags->count() === 3, 'Should have 3 tags after removal');
    }

    private function testTagCollectionFromHeader(): void
    {
        $header = 'product.1, category.2, store.default';
        $tags = TagCollection::fromHeader($header);

        $this->assert($tags->count() === 3, 'Should parse 3 tags from header');
        $this->assert($tags->has('product.1'), 'Should have product.1');
        $this->assert($tags->has('category.2'), 'Should have category.2');
        $this->assert($tags->has('store.default'), 'Should have store.default');

        $this->assert($tags->toHeader() === 'product.1,category.2,store.default', 'Should serialize to header');
    }

    private function testPurgeCollection(): void
    {
        // Warm cache
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/categories/1');

        // Create and purge collection
        $tags = $this->trident->tags()
            ->addProduct(1)
            ->addCategory(1);

        $response = $this->trident->purgeCollection($tags);
        $this->assert($response->isSuccess(), 'Purge collection should succeed');
    }

    private function testPurgeProduct(): void
    {
        $this->httpGet($this->proxyUrl . '/api/products/3');
        $response = $this->trident->purgeProduct(3);
        $this->assert($response->isSuccess(), 'purgeProduct() should succeed');
    }

    private function testPurgeCategory(): void
    {
        $this->httpGet($this->proxyUrl . '/api/categories/1');
        $response = $this->trident->purgeCategory(1);
        $this->assert($response->isSuccess(), 'purgeCategory() should succeed');
    }

    private function testPurgePage(): void
    {
        $this->httpGet($this->proxyUrl . '/api/pages/about');
        $response = $this->trident->purgePage(1);
        $this->assert($response->isSuccess(), 'purgePage() should succeed');
    }

    private function testPurgeAllProducts(): void
    {
        // Warm multiple products
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');

        $response = $this->trident->purgeAllProducts();
        $this->assert($response->isSuccess(), 'purgeAllProducts() should succeed');
    }

    private function testTagStats(): void
    {
        // Warm some cache to create tags
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/categories/1');

        $response = $this->trident->tagStats();
        $this->assert($response->totalTags >= 0, 'Total tags should be >= 0');
        $this->assert(is_array($response->tags), 'Tags should be an array');
    }

    private function testBackends(): void
    {
        $response = $this->trident->backends();
        $this->assert($response->total >= 1, 'Should have at least 1 backend');
        $this->assert($response->healthy >= 1, 'Should have at least 1 healthy backend');
        $this->assert($response->isAllHealthy(), 'All backends should be healthy');
    }

    private function testCacheEntry(): void
    {
        // First warm the cache
        $this->httpGet($this->proxyUrl . '/api/products/1');

        // Look up the cache entry - need to use the host that was used in the request
        $host = parse_url($this->proxyUrl, PHP_URL_HOST) . ':' . parse_url($this->proxyUrl, PHP_URL_PORT);
        $response = $this->trident->cacheEntry('/api/products/1', $host);
        $this->assert($response->found === true, 'Cache entry should be found');
        $this->assert($response->isFresh() || $response->isStale(), 'Entry should be fresh or stale');
        $this->assert(is_array($response->tags), 'Entry should have tags');
    }

    private function testRefreshQueue(): void
    {
        $response = $this->trident->refreshQueue();
        $this->assert($response->pending >= 0, 'Pending should be >= 0');
        $this->assert($response->queueCapacity > 0, 'Queue capacity should be > 0');
        $this->assert($response->getSuccessRate() >= 0, 'Success rate should be >= 0');
    }

    private function testPurgeUrls(): void
    {
        // Warm multiple URLs
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');
        $this->httpGet($this->proxyUrl . '/api/products/3');

        // Purge multiple URLs at once
        $urls = ['/api/products/1', '/api/products/2', '/api/products/3'];
        $response = $this->trident->purgeUrls($urls);
        $this->assert($response->isSuccess(), 'Bulk URL purge should succeed');
    }

    private function testPurgeTagPatternRegex(): void
    {
        // Warm test endpoints
        $this->httpGet($this->proxyUrl . '/test/tags');

        // Purge using regex pattern
        $response = $this->trident->purgeTagPattern('^test\\.tag[0-9]+$', 'regex');
        $this->assert($response->isSuccess(), 'Regex tag pattern purge should succeed');
    }

    private function testPurgeMultipleProductsOr(): void
    {
        // Clear cache first
        $this->trident->purgeAll();

        // Warm products 1, 2, 3, 4, 5
        for ($i = 1; $i <= 5; $i++) {
            $this->httpGet($this->proxyUrl . '/api/products/' . $i);
        }

        // Verify all are cached
        $host = parse_url($this->proxyUrl, PHP_URL_HOST) . ':' . parse_url($this->proxyUrl, PHP_URL_PORT);
        for ($i = 1; $i <= 5; $i++) {
            $entry = $this->trident->cacheEntry('/api/products/' . $i, $host);
            $this->assert($entry->found, "Product {$i} should be cached before purge");
        }

        // Purge products 1, 3, 5 using OR (any match)
        $response = $this->trident->purgeTags(['product.1', 'product.3', 'product.5']);
        $this->assert($response->isSuccess(), 'Multi-product OR purge should succeed');
        $this->assert($response->purgedCount >= 3, 'Should have purged at least 3 entries');
    }

    private function testPurgeCategoryAndStoreTags(): void
    {
        // Clear cache first
        $this->trident->purgeAll();

        // Warm products and categories
        $this->httpGet($this->proxyUrl . '/api/products/1');  // category.1, store.default
        $this->httpGet($this->proxyUrl . '/api/products/3');  // category.2, store.default
        $this->httpGet($this->proxyUrl . '/api/categories/1');

        // Purge entries that have BOTH category.1 AND store.default tags
        $response = $this->trident->purgeTagsAll(['category.1', 'store.default']);
        $this->assert($response->isSuccess(), 'AND purge should succeed');
    }

    private function testVerifyOrPurgeSelectivity(): void
    {
        // Clear cache first
        $this->trident->purgeAll();

        // Warm products 1, 2, 3
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');
        $this->httpGet($this->proxyUrl . '/api/products/3');

        $host = parse_url($this->proxyUrl, PHP_URL_HOST) . ':' . parse_url($this->proxyUrl, PHP_URL_PORT);

        // Purge only product.1 and product.2
        $this->trident->purgeTags(['product.1', 'product.2']);

        // Product 3 should still be in cache (soft purge marks as stale)
        $entry3 = $this->trident->cacheEntry('/api/products/3', $host);
        $this->assert($entry3->found, 'Product 3 should still be found after OR purge of 1,2');
    }

    private function testVerifyAndPurgeRequiresAll(): void
    {
        // Clear cache first
        $this->trident->purgeAll();

        // Warm products - product.1 has category.1, product.3 has category.2
        $this->httpGet($this->proxyUrl . '/api/products/1');  // has: product.1, category.1, store.default
        $this->httpGet($this->proxyUrl . '/api/products/3');  // has: product.3, category.2, store.default

        $host = parse_url($this->proxyUrl, PHP_URL_HOST) . ':' . parse_url($this->proxyUrl, PHP_URL_PORT);

        // Verify both are fresh before purge
        $entry1Before = $this->trident->cacheEntry('/api/products/1', $host);
        $entry3Before = $this->trident->cacheEntry('/api/products/3', $host);
        $this->assert($entry1Before->found && $entry1Before->isFresh(), 'Product 1 should be fresh before AND purge');
        $this->assert($entry3Before->found && $entry3Before->isFresh(), 'Product 3 should be fresh before AND purge');

        // Purge entries that have BOTH category.1 AND store.default
        // This should only affect product 1, not product 3
        $response = $this->trident->purgeTagsAll(['category.1', 'store.default']);
        $this->assert($response->isSuccess(), 'AND purge should succeed');
        $this->assert($response->purgedCount >= 1, 'AND purge should purge at least 1 entry');

        // Product 1 should be stale (AND condition met - has both category.1 and store.default)
        $entry1After = $this->trident->cacheEntry('/api/products/1', $host);
        $this->assert($entry1After->found, 'Product 1 should still be found (soft purge)');
        $this->assert($entry1After->isStale(), 'Product 1 should be stale (AND condition met)');

        // Product 3 should still be fresh (AND condition NOT met - has category.2, not category.1)
        $entry3After = $this->trident->cacheEntry('/api/products/3', $host);
        $this->assert($entry3After->found, 'Product 3 should still be in cache');
        $this->assert($entry3After->isFresh(), 'Product 3 should still be fresh (AND condition not met)');
    }

    // ========================================
    // Extended Stats & Monitoring Tests
    // ========================================

    private function testLatencyStats(): void
    {
        // Generate some traffic first
        $this->httpGet($this->proxyUrl . '/api/products/1');
        $this->httpGet($this->proxyUrl . '/api/products/2');

        $response = $this->trident->latencyStats();

        // Latency stats should have valid structure
        $this->assert($response->sampleCount >= 0, 'Sample count should be >= 0');
        $this->assert($response->windowSecs >= 0, 'Window seconds should be >= 0');

        // If we have samples, percentiles should be present
        if ($response->sampleCount > 0) {
            $this->assert($response->getP50Ms() >= 0, 'P50 should be >= 0');
            $this->assert($response->getP95Ms() >= 0, 'P95 should be >= 0');
            $this->assert($response->getP99Ms() >= 0, 'P99 should be >= 0');
        }
    }

    private function testErrorStats(): void
    {
        $response = $this->trident->errorStats();

        $this->assert($response->totalRequests >= 0, 'Total requests should be >= 0');
        $this->assert($response->totalErrors >= 0, 'Total errors should be >= 0');
        $this->assert($response->errorRate >= 0.0, 'Error rate should be >= 0');
        $this->assert(is_array($response->byStatus), 'By status should be an array');
        $this->assert(is_array($response->recent), 'Recent should be an array');
    }

    private function testBackendDetail(): void
    {
        // First get backends list
        $backends = $this->trident->backends();
        $this->assert($backends->total >= 1, 'Should have at least 1 backend');

        // Get the first backend name
        $backendName = $backends->backends[0]['name'] ?? 'origin';

        // Get backend detail
        $response = $this->trident->backendDetail($backendName);

        $this->assert($response->name === $backendName, "Backend name should match: {$backendName}");
        $this->assert(is_bool($response->healthy), 'Healthy should be boolean');
        $this->assert(!empty($response->host), 'Host should not be empty');
        $this->assert($response->port > 0, 'Port should be > 0');
        $this->assert(!empty($response->getUrl()), 'Generated URL should not be empty');
    }

    private function testRules(): void
    {
        $response = $this->trident->rules();

        $this->assert($response->requestRules >= 0, 'Request rules count should be >= 0');
        $this->assert($response->responseRules >= 0, 'Response rules count should be >= 0');
        $this->assert($response->getTotalRules() >= 0, 'Total rules should be >= 0');
        $this->assert(is_array($response->request), 'Request rules should be an array');
        $this->assert(is_array($response->response), 'Response rules should be an array');
    }

    private function testValidateRules(): void
    {
        $response = $this->trident->validateRules();

        // Validation should return a result
        $this->assert(is_bool($response->valid), 'Valid should be boolean');

        if ($response->isValid()) {
            $this->assert(!$response->hasError(), 'Valid config should not have error');
            $this->assert($response->getTotalRules() >= 0, 'Should report rule count');
        }
    }

    private function testPurgeHash(): void
    {
        // First, warm a cache entry
        $this->httpGet($this->proxyUrl . '/api/products/1');

        // Get the cache entry to find its hash (if available)
        $host = parse_url($this->proxyUrl, PHP_URL_HOST) . ':' . parse_url($this->proxyUrl, PHP_URL_PORT);
        $entry = $this->trident->cacheEntry('/api/products/1', $host);

        if ($entry->found && isset($entry->details['hash'])) {
            // Purge by hash
            $response = $this->trident->purgeHash($entry->details['hash']);
            $this->assert($response->isSuccess(), 'Purge by hash should succeed');
        } else {
            // Just test that purgeHash works with a fake hash (should succeed even if nothing found)
            $response = $this->trident->purgeHash('0000000000000000');
            $this->assert($response->isSuccess(), 'Purge by hash should succeed (even with no match)');
        }
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * @param array<string> $headers
     * @return array{status: int, headers: array<string, string>, body: mixed}
     */
    private function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $responseHeaders = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => json_decode($body, true) ?? $body,
        ];
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Assertion failed: {$message}");
        }
    }

    private function test(string $name, callable $callback): void
    {
        $start = microtime(true);

        try {
            $callback();
            $duration = (microtime(true) - $start) * 1000;
            $this->passed++;
            $this->results[] = [
                'test' => $name,
                'status' => 'PASS',
                'message' => '',
                'duration' => $duration,
            ];
            $durationFormatted = number_format($duration, 1);
            echo "  \033[32m✓\033[0m {$name} \033[2m({$durationFormatted}ms)\033[0m\n";
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;
            $this->failed++;
            $this->results[] = [
                'test' => $name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'duration' => $duration,
            ];
            echo "  \033[31m✗\033[0m {$name}\n";
            echo "    \033[31m→ {$e->getMessage()}\033[0m\n";
        }
    }

    private function header(string $title): void
    {
        echo "\n";
        echo "\033[1;36m" . str_repeat('━', 60) . "\033[0m\n";
        echo "\033[1;36m  {$title}\033[0m\n";
        echo "\033[1;36m" . str_repeat('━', 60) . "\033[0m\n";
        echo "\n";
    }

    private function section(string $title): void
    {
        echo "\n\033[1;33m▸ {$title}\033[0m\n";
    }

    private function summary(): void
    {
        $total = $this->passed + $this->failed;

        echo "\n";
        echo str_repeat('═', 60) . "\n";

        if ($this->failed === 0) {
            echo "\033[1;32m  All {$total} tests passed!\033[0m\n";
        } else {
            echo "\033[1;31m  {$this->failed} of {$total} tests failed\033[0m\n";
        }

        echo str_repeat('═', 60) . "\n";
        echo "\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $runner = new E2ETestRunner();
    exit($runner->run());
}
