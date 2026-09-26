<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident;

use Qoliber\Trident\Cache\TagCollection;
use Qoliber\Trident\Cache\TagResolver;
use Qoliber\Trident\Client\TridentClientInterface;
use Qoliber\Trident\Events\EventStream;
use Qoliber\Trident\Response\BackendActionResponse;
use Qoliber\Trident\Response\BackendDetailResponse;
use Qoliber\Trident\Response\BackendsResponse;
use Qoliber\Trident\Response\BanCreateResponse;
use Qoliber\Trident\Response\BansResponse;
use Qoliber\Trident\Response\CacheEntriesResponse;
use Qoliber\Trident\Response\CacheEntryResponse;
use Qoliber\Trident\Response\CacheStatsResponse;
use Qoliber\Trident\Response\CacheTagsResponse;
use Qoliber\Trident\Response\ConfigResponse;
use Qoliber\Trident\Response\ConnectionsResponse;
use Qoliber\Trident\Response\DiscoveryDetailResponse;
use Qoliber\Trident\Response\DiscoveryListResponse;
use Qoliber\Trident\Response\DiscoveryRefreshResponse;
use Qoliber\Trident\Response\ErrorStatsResponse;
use Qoliber\Trident\Response\HealthResponse;
use Qoliber\Trident\Response\LatencyStatsResponse;
use Qoliber\Trident\Response\LaunchResponse;
use Qoliber\Trident\Response\LaunchStatusResponse;
use Qoliber\Trident\Response\MemoryStatsResponse;
use Qoliber\Trident\Response\ProtectionStatsResponse;
use Qoliber\Trident\Response\PurgePreviewResponse;
use Qoliber\Trident\Response\PurgeResponse;
use Qoliber\Trident\Response\ReadyResponse;
use Qoliber\Trident\Response\RefreshQueueResponse;
use Qoliber\Trident\Response\ReloadResponse;
use Qoliber\Trident\Response\RulesResponse;
use Qoliber\Trident\Response\RulesValidateResponse;
use Qoliber\Trident\Response\SnapshotResponse;
use Qoliber\Trident\Response\TagStatsResponse;
use Qoliber\Trident\Response\TopUrlsResponse;

/**
 * Main facade for Trident operations
 */
class TridentManager
{
    private TridentClientInterface $client;
    private TagResolver $tagResolver;

    public function __construct(
        TridentClientInterface $client,
        ?TagResolver $tagResolver = null
    ) {
        $this->client = $client;
        $this->tagResolver = $tagResolver ?? new TagResolver();
    }

    public function getClient(): TridentClientInterface
    {
        return $this->client;
    }

    public function getTagResolver(): TagResolver
    {
        return $this->tagResolver;
    }

    // ========================================
    // Health & Stats
    // ========================================

    public function health(): HealthResponse
    {
        return $this->client->health();
    }

    public function isHealthy(): bool
    {
        try {
            return $this->client->health()->isHealthy();
        } catch (\Throwable) {
            return false;
        }
    }

    public function ready(): ReadyResponse
    {
        return $this->client->ready();
    }

    public function isReady(): bool
    {
        try {
            return $this->client->ready()->isReady();
        } catch (\Throwable) {
            return false;
        }
    }

    public function stats(): CacheStatsResponse
    {
        return $this->client->stats();
    }

    public function topUrls(int $limit = 20, string $sort = 'requests'): TopUrlsResponse
    {
        return $this->client->topUrls($limit, $sort);
    }

    public function tagStats(): TagStatsResponse
    {
        return $this->client->tagStats();
    }

    public function refreshQueue(): RefreshQueueResponse
    {
        return $this->client->refreshQueue();
    }

    public function cacheEntry(string $url, ?string $host = null, string $method = 'GET'): CacheEntryResponse
    {
        return $this->client->cacheEntry($url, $host, $method);
    }

    public function latencyStats(): LatencyStatsResponse
    {
        return $this->client->latencyStats();
    }

    public function errorStats(int $limit = 10): ErrorStatsResponse
    {
        return $this->client->errorStats($limit);
    }

    public function backends(): BackendsResponse
    {
        return $this->client->backends();
    }

    public function drainBackend(string $name): BackendActionResponse
    {
        return $this->client->drainBackend($name);
    }

    public function restoreBackend(string $name): BackendActionResponse
    {
        return $this->client->restoreBackend($name);
    }

    public function backendDetail(string $name): BackendDetailResponse
    {
        return $this->client->backendDetail($name);
    }

    // ========================================
    // Banning
    // ========================================

    public function bans(int $limit = 100): BansResponse
    {
        return $this->client->bans($limit);
    }

    public function createBan(string $pattern, ?string $ttl = null): BanCreateResponse
    {
        return $this->client->createBan($pattern, $ttl);
    }

    public function deleteBan(string $id): bool
    {
        return $this->client->deleteBan($id);
    }

    // ========================================
    // Configuration
    // ========================================

    public function reload(): ReloadResponse
    {
        return $this->client->reload();
    }

    public function config(): ConfigResponse
    {
        return $this->client->config();
    }

    public function rules(): RulesResponse
    {
        return $this->client->rules();
    }

    public function validateRules(): RulesValidateResponse
    {
        return $this->client->validateRules();
    }

    // ========================================
    // Purge Operations
    // ========================================

    public function purgeUrl(string $url, bool $soft = false): PurgeResponse
    {
        return $this->client->purgeUrl($url, $soft);
    }

    /**
     * @param array<string> $urls
     */
    public function purgeUrls(array $urls, bool $soft = false): PurgeResponse
    {
        return $this->client->purgeUrls($urls, $soft);
    }

    public function purgeUrlPattern(string $pattern, bool $soft = false): PurgeResponse
    {
        return $this->client->purgeUrlPattern($pattern, $soft);
    }

    public function purgeTag(string $tag): PurgeResponse
    {
        return $this->client->purgeTag($tag);
    }

    /**
     * @param array<string> $tags
     */
    public function purgeTags(array $tags): PurgeResponse
    {
        return $this->client->purgeTags($tags);
    }

    /**
     * @param array<string> $tags
     */
    public function purgeTagsAll(array $tags): PurgeResponse
    {
        return $this->client->purgeTagsAll($tags);
    }

    public function purgeTagPattern(string $pattern, string $patternType = 'wildcard'): PurgeResponse
    {
        return $this->client->purgeTagPattern($pattern, $patternType);
    }

    public function purgeAll(): PurgeResponse
    {
        return $this->client->purgeAll();
    }

    public function purgeHash(string $hash, bool $soft = false): PurgeResponse
    {
        return $this->client->purgeHash($hash, $soft);
    }

    public function ban(string $pattern): PurgeResponse
    {
        return $this->client->ban($pattern);
    }

    // ========================================
    // Entity-based Purging
    // ========================================

    /**
     * Purge cache for an entity using the tag resolver
     */
    public function purgeEntity(string $type, mixed $entity): PurgeResponse
    {
        $tags = $this->tagResolver->resolve($type, $entity);

        return $this->client->purgeTags($tags);
    }

    /**
     * Purge cache for multiple entities
     *
     * @param iterable<mixed> $entities
     */
    public function purgeEntities(string $type, iterable $entities): PurgeResponse
    {
        $tags = $this->tagResolver->resolveMany($type, $entities);

        return $this->client->purgeTags($tags);
    }

    // ========================================
    // Convenience Methods
    // ========================================

    /**
     * Purge product cache
     */
    public function purgeProduct(int|string $id): PurgeResponse
    {
        return $this->purgeTag("product.{$id}");
    }

    /**
     * Purge category cache
     */
    public function purgeCategory(int|string $id): PurgeResponse
    {
        return $this->purgeTag("category.{$id}");
    }

    /**
     * Purge page cache
     */
    public function purgePage(int|string $id): PurgeResponse
    {
        return $this->purgeTag("page.{$id}");
    }

    /**
     * Purge all products cache
     */
    public function purgeAllProducts(): PurgeResponse
    {
        return $this->purgeTagPattern('product.*');
    }

    /**
     * Purge all categories cache
     */
    public function purgeAllCategories(): PurgeResponse
    {
        return $this->purgeTagPattern('category.*');
    }

    // ========================================
    // Tag Collection Factory
    // ========================================

    /**
     * Create a new tag collection
     */
    public function tags(): TagCollection
    {
        return new TagCollection();
    }

    /**
     * Purge a tag collection
     */
    public function purgeCollection(TagCollection $tags): PurgeResponse
    {
        return $this->client->purgeTags($tags->all());
    }

    // ========================================
    // Launch Mode (Enterprise)
    // ========================================

    /**
     * Start launch mode with URL warming
     *
     * @param array{name?: string, urls?: array<string>, sitemap_url?: string, maintenance?: array{title?: string, message?: string}, options?: array{timeout?: string, auto_complete?: bool, concurrency?: int}} $options
     */
    public function launchStart(array $options): LaunchResponse
    {
        return $this->client->launchStart($options);
    }

    /**
     * Get launch status
     */
    public function launchStatus(string $launchId): LaunchStatusResponse
    {
        return $this->client->launchStatus($launchId);
    }

    /**
     * Complete launch (go live)
     */
    public function launchComplete(string $launchId): LaunchResponse
    {
        return $this->client->launchComplete($launchId);
    }

    /**
     * Abort launch
     */
    public function launchAbort(string $launchId, ?string $reason = null): LaunchResponse
    {
        return $this->client->launchAbort($launchId, $reason);
    }

    // ========================================
    // Cache Browsing
    // ========================================

    /**
     * List cache entries with pagination
     */
    public function cacheEntries(int $limit = 100, int $offset = 0, ?string $filter = null): CacheEntriesResponse
    {
        return $this->client->cacheEntries($limit, $offset, $filter);
    }

    /**
     * List cache tags with pagination
     */
    public function cacheTags(int $limit = 100, int $offset = 0, ?string $filter = null): CacheTagsResponse
    {
        return $this->client->cacheTags($limit, $offset, $filter);
    }

    /**
     * Preview purge operation (dry-run)
     */
    public function purgePreview(string $pattern, string $type = 'url'): PurgePreviewResponse
    {
        return $this->client->purgePreview($pattern, $type);
    }

    // ========================================
    // Advanced Statistics
    // ========================================

    /**
     * Get backend protection/circuit breaker statistics
     */
    public function protectionStats(): ProtectionStatsResponse
    {
        return $this->client->protectionStats();
    }

    /**
     * Get detailed memory breakdown
     */
    public function memoryStats(): MemoryStatsResponse
    {
        return $this->client->memoryStats();
    }

    /**
     * Get backend connection pool statistics
     */
    public function connections(): ConnectionsResponse
    {
        return $this->client->connections();
    }

    // ========================================
    // Cache Persistence
    // ========================================

    /**
     * Create a cache snapshot for persistence
     */
    public function snapshot(?string $path = null): SnapshotResponse
    {
        return $this->client->snapshot($path);
    }

    // ========================================
    // DNS Discovery
    // ========================================

    /**
     * List DNS discovery backends
     */
    public function discoveryList(): DiscoveryListResponse
    {
        return $this->client->discoveryList();
    }

    /**
     * Get detailed DNS discovery information for a backend
     */
    public function discoveryDetail(string $name): DiscoveryDetailResponse
    {
        return $this->client->discoveryDetail($name);
    }

    /**
     * Force DNS refresh for a backend
     */
    public function discoveryRefresh(string $name): DiscoveryRefreshResponse
    {
        return $this->client->discoveryRefresh($name);
    }

    // ========================================
    // Server-Sent Events (SSE)
    // ========================================

    /**
     * Get an event stream for real-time SSE events
     *
     * Usage:
     *   $stream = $trident->events();
     *   foreach ($stream->requests() as $event) {
     *       echo $event->getType() . ': ' . json_encode($event->getData());
     *   }
     */
    public function events(): EventStream
    {
        return $this->client->events();
    }
}
