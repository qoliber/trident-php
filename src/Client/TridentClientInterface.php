<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Client;

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
use Qoliber\Trident\Events\EventStream;
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

interface TridentClientInterface
{
    // ========================================
    // Health & Status
    // ========================================

    /**
     * Check if Trident server is healthy
     */
    public function health(): HealthResponse;

    /**
     * Check if Trident is ready (backends healthy)
     */
    public function ready(): ReadyResponse;

    /**
     * Get cache statistics
     */
    public function stats(): CacheStatsResponse;

    /**
     * Get top URLs by requests, bytes, etc.
     */
    public function topUrls(int $limit = 20, string $sort = 'requests'): TopUrlsResponse;

    /**
     * Get tag statistics
     */
    public function tagStats(): TagStatsResponse;

    /**
     * Get refresh queue status
     */
    public function refreshQueue(): RefreshQueueResponse;

    /**
     * Get cache entry by URL
     */
    public function cacheEntry(string $url, ?string $host = null, string $method = 'GET'): CacheEntryResponse;

    /**
     * Get latency statistics (percentiles)
     */
    public function latencyStats(): LatencyStatsResponse;

    /**
     * Get error statistics
     */
    public function errorStats(int $limit = 10): ErrorStatsResponse;

    // ========================================
    // Backend Management
    // ========================================

    /**
     * Get backend health status
     */
    public function backends(): BackendsResponse;

    /**
     * Drain a backend (stop sending new requests)
     */
    public function drainBackend(string $name): BackendActionResponse;

    /**
     * Restore a drained backend
     */
    public function restoreBackend(string $name): BackendActionResponse;

    /**
     * Get detailed backend information
     */
    public function backendDetail(string $name): BackendDetailResponse;

    // ========================================
    // Banning
    // ========================================

    /**
     * Get active bans/soft-purges
     */
    public function bans(int $limit = 100): BansResponse;

    /**
     * Create a ban rule
     */
    public function createBan(string $pattern, string $type = 'url'): BanCreateResponse;

    /**
     * Delete a ban by ID
     */
    public function deleteBan(string $id): bool;

    // ========================================
    // Configuration
    // ========================================

    /**
     * Hot reload configuration
     */
    public function reload(): ReloadResponse;

    /**
     * Get current configuration (sanitized)
     */
    public function config(): ConfigResponse;

    /**
     * Get active rules with statistics
     */
    public function rules(): RulesResponse;

    /**
     * Validate current configuration file
     */
    public function validateRules(): RulesValidateResponse;

    // ========================================
    // Purge Operations
    // ========================================

    /**
     * Purge cache by URL
     */
    public function purgeUrl(string $url, bool $soft = false): PurgeResponse;

    /**
     * Purge multiple URLs
     *
     * @param array<string> $urls
     */
    public function purgeUrls(array $urls, bool $soft = false): PurgeResponse;

    /**
     * Purge URLs matching regex pattern
     */
    public function purgeUrlPattern(string $pattern, bool $soft = false): PurgeResponse;

    /**
     * Purge cache by single tag
     */
    public function purgeTag(string $tag): PurgeResponse;

    /**
     * Purge cache by multiple tags (OR match - any tag matches)
     *
     * @param array<string> $tags
     */
    public function purgeTags(array $tags): PurgeResponse;

    /**
     * Purge cache by multiple tags with AND match (all tags must match)
     *
     * @param array<string> $tags
     */
    public function purgeTagsAll(array $tags): PurgeResponse;

    /**
     * Purge cache by tag pattern (wildcard or regex)
     */
    public function purgeTagPattern(string $pattern, string $patternType = 'wildcard'): PurgeResponse;

    /**
     * Purge all cache entries
     */
    public function purgeAll(): PurgeResponse;

    /**
     * Purge cache entry by hash
     */
    public function purgeHash(string $hash, bool $soft = false): PurgeResponse;

    /**
     * Ban cache entries by URL pattern (legacy, use createBan)
     */
    public function ban(string $pattern): PurgeResponse;

    // ========================================
    // Launch Mode (Enterprise)
    // ========================================

    /**
     * Start launch mode with URL warming
     *
     * @param array{name?: string, urls?: array<string>, sitemap_url?: string, maintenance?: array{title?: string, message?: string}, options?: array{timeout?: string, auto_complete?: bool, concurrency?: int}} $options
     */
    public function launchStart(array $options): LaunchResponse;

    /**
     * Get launch status
     */
    public function launchStatus(string $launchId): LaunchStatusResponse;

    /**
     * Complete launch (go live)
     */
    public function launchComplete(string $launchId): LaunchResponse;

    /**
     * Abort launch
     */
    public function launchAbort(string $launchId, ?string $reason = null): LaunchResponse;

    // ========================================
    // Cache Browsing
    // ========================================

    /**
     * List cache entries with pagination
     */
    public function cacheEntries(int $limit = 100, int $offset = 0, ?string $filter = null): CacheEntriesResponse;

    /**
     * List cache tags with pagination
     */
    public function cacheTags(int $limit = 100, int $offset = 0, ?string $filter = null): CacheTagsResponse;

    /**
     * Preview purge operation (dry-run)
     */
    public function purgePreview(string $pattern, string $type = 'url'): PurgePreviewResponse;

    // ========================================
    // Advanced Statistics
    // ========================================

    /**
     * Get backend protection/circuit breaker statistics
     */
    public function protectionStats(): ProtectionStatsResponse;

    /**
     * Get detailed memory breakdown
     */
    public function memoryStats(): MemoryStatsResponse;

    /**
     * Get backend connection pool statistics
     */
    public function connections(): ConnectionsResponse;

    // ========================================
    // Cache Persistence
    // ========================================

    /**
     * Create a cache snapshot for persistence
     */
    public function snapshot(?string $path = null): SnapshotResponse;

    // ========================================
    // DNS Discovery
    // ========================================

    /**
     * List DNS discovery backends
     */
    public function discoveryList(): DiscoveryListResponse;

    /**
     * Get detailed DNS discovery information for a backend
     */
    public function discoveryDetail(string $name): DiscoveryDetailResponse;

    /**
     * Force DNS refresh for a backend
     */
    public function discoveryRefresh(string $name): DiscoveryRefreshResponse;

    // ========================================
    // Server-Sent Events (SSE)
    // ========================================

    /**
     * Get an event stream for real-time SSE events
     */
    public function events(): EventStream;
}
