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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qoliber\Trident\Exception\TridentException;
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

class TridentClient implements TridentClientInterface
{
    private string $baseUrl;
    private ?string $apiKey;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;

    /** HTTP status of the last response, for acknowledgement checks. */
    private ?int $lastStatusCode = null;

    public function __construct(
        string $baseUrl,
        ?string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger ?? new NullLogger();
    }

    // ========================================
    // Health & Status
    // ========================================

    public function health(): HealthResponse
    {
        $response = $this->request('GET', '/admin/health');

        return HealthResponse::fromArray($response);
    }

    public function ready(): ReadyResponse
    {
        $response = $this->request('GET', '/admin/ready');

        return ReadyResponse::fromArray($response);
    }

    public function stats(): CacheStatsResponse
    {
        $response = $this->request('GET', '/admin/stats');

        return CacheStatsResponse::fromArray($response);
    }

    public function topUrls(int $limit = 20, string $sort = 'requests'): TopUrlsResponse
    {
        $endpoint = '/admin/stats/top?limit=' . $limit . '&sort=' . urlencode($sort);
        $response = $this->request('GET', $endpoint);

        return TopUrlsResponse::fromArray($response);
    }

    public function tagStats(): TagStatsResponse
    {
        $response = $this->request('GET', '/admin/tags/stats');

        return TagStatsResponse::fromArray($response);
    }

    public function refreshQueue(): RefreshQueueResponse
    {
        $response = $this->request('GET', '/admin/refresh/queue');

        return RefreshQueueResponse::fromArray($response);
    }

    public function cacheEntry(string $url, ?string $host = null, string $method = 'GET'): CacheEntryResponse
    {
        $params = ['url' => $url, 'method' => $method];
        if ($host !== null) {
            $params['host'] = $host;
        }
        $endpoint = '/admin/cache/entry?' . http_build_query($params);
        $response = $this->request('GET', $endpoint);

        return CacheEntryResponse::fromArray($response);
    }

    public function latencyStats(): LatencyStatsResponse
    {
        $response = $this->request('GET', '/admin/stats/latency');

        return LatencyStatsResponse::fromArray($response);
    }

    public function errorStats(int $limit = 10): ErrorStatsResponse
    {
        $endpoint = '/admin/stats/errors?limit=' . $limit;
        $response = $this->request('GET', $endpoint);

        return ErrorStatsResponse::fromArray($response);
    }

    // ========================================
    // Backend Management
    // ========================================

    public function backends(): BackendsResponse
    {
        $response = $this->request('GET', '/admin/backends');

        return BackendsResponse::fromArray($response);
    }

    public function drainBackend(string $name): BackendActionResponse
    {
        $body = ['name' => $name];
        $response = $this->request('POST', '/admin/backends/drain', $body);

        return BackendActionResponse::fromArray($response);
    }

    public function restoreBackend(string $name): BackendActionResponse
    {
        $body = ['name' => $name];
        $response = $this->request('POST', '/admin/backends/restore', $body);

        return BackendActionResponse::fromArray($response);
    }

    public function backendDetail(string $name): BackendDetailResponse
    {
        $endpoint = '/admin/backends/detail?name=' . urlencode($name);
        $response = $this->request('GET', $endpoint);

        return BackendDetailResponse::fromArray($response);
    }

    // ========================================
    // Banning
    // ========================================

    public function bans(int $limit = 100): BansResponse
    {
        $endpoint = '/admin/bans?limit=' . $limit;
        $response = $this->request('GET', $endpoint);

        return BansResponse::fromArray($response);
    }

    public function createBan(string $pattern, string $type = 'url'): BanCreateResponse
    {
        $body = [
            'type' => $type,
            'pattern' => $pattern,
        ];

        $response = $this->request('POST', '/admin/bans', $body);

        return BanCreateResponse::fromArray($response);
    }

    public function deleteBan(string $id): bool
    {
        $response = $this->request('DELETE', '/admin/bans/' . urlencode($id));

        return ($response['success'] ?? false) || ($response['status_code'] ?? 0) < 400;
    }

    // ========================================
    // Configuration
    // ========================================

    public function reload(): ReloadResponse
    {
        $response = $this->request('POST', '/admin/config/reload');

        return ReloadResponse::fromArray($response);
    }

    public function config(): ConfigResponse
    {
        $response = $this->request('GET', '/admin/config');

        return ConfigResponse::fromArray($response);
    }

    public function rules(): RulesResponse
    {
        $response = $this->request('GET', '/admin/rules');

        return RulesResponse::fromArray($response);
    }

    public function validateRules(): RulesValidateResponse
    {
        $response = $this->request('POST', '/admin/rules/validate');

        return RulesValidateResponse::fromArray($response);
    }

    // ========================================
    // Purge Operations
    // ========================================

    public function purgeUrl(string $url, bool $soft = false): PurgeResponse
    {
        $body = [
            'url' => $url,
        ];

        if (!$soft) {
            $body['mode'] = 'hard';
        }

        $response = $this->request('POST', '/admin/purge/url', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeUrls(array $urls, bool $soft = false): PurgeResponse
    {
        $body = [
            'urls' => $urls,
        ];

        if (!$soft) {
            $body['mode'] = 'hard';
        }

        $response = $this->request('POST', '/admin/purge/urls', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeUrlPattern(string $pattern, bool $soft = false): PurgeResponse
    {
        $body = [
            'pattern' => $pattern,
        ];

        if (!$soft) {
            $body['mode'] = 'hard';
        }

        $response = $this->request('POST', '/admin/purge/pattern', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeTag(string $tag): PurgeResponse
    {
        $body = [
            'tag' => $tag,
        ];

        $response = $this->request('POST', '/admin/purge/tag', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeTags(array $tags): PurgeResponse
    {
        $body = [
            'tags' => $tags,
            'match_mode' => 'any',
        ];

        $response = $this->request('POST', '/admin/purge/tags', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeTagsAll(array $tags): PurgeResponse
    {
        $body = [
            'tags' => $tags,
            'match_mode' => 'all',
        ];

        $response = $this->request('POST', '/admin/purge/tags', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeTagPattern(string $pattern, string $patternType = 'wildcard'): PurgeResponse
    {
        $body = [
            'pattern' => $pattern,
            'type' => $patternType,
        ];

        $response = $this->request('POST', '/admin/purge/tag/pattern', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeAll(): PurgeResponse
    {
        $body = [
            'confirm' => true,
        ];

        $response = $this->request('POST', '/admin/cache/clear', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeHash(string $hash, bool $soft = false): PurgeResponse
    {
        $body = [
            'hash' => $hash,
        ];

        if (!$soft) {
            $body['mode'] = 'hard';
        }

        $response = $this->request('POST', '/admin/purge/hash', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    public function ban(string $pattern): PurgeResponse
    {
        $body = [
            'pattern' => $pattern,
        ];

        $response = $this->request('POST', '/admin/purge/url', $body);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    // ========================================
    // Launch Mode (Enterprise)
    // ========================================

    public function launchStart(array $options): LaunchResponse
    {
        $response = $this->request('POST', '/admin/launch/start', $options);

        return LaunchResponse::fromArray($response);
    }

    public function launchStatus(string $launchId): LaunchStatusResponse
    {
        $response = $this->request('GET', '/admin/launch/status/' . urlencode($launchId));

        return LaunchStatusResponse::fromArray($response);
    }

    public function launchComplete(string $launchId): LaunchResponse
    {
        $response = $this->request('POST', '/admin/launch/complete/' . urlencode($launchId));

        return LaunchResponse::fromArray($response);
    }

    public function launchAbort(string $launchId, ?string $reason = null): LaunchResponse
    {
        $body = [];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        $response = $this->request('POST', '/admin/launch/abort/' . urlencode($launchId), $body ?: null);

        return LaunchResponse::fromArray($response);
    }

    // ========================================
    // Cache Browsing
    // ========================================

    public function cacheEntries(int $limit = 100, int $offset = 0, ?string $filter = null): CacheEntriesResponse
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        if ($filter !== null) {
            $params['filter'] = $filter;
        }
        $endpoint = '/admin/cache/entries?' . http_build_query($params);
        $response = $this->request('GET', $endpoint);

        return CacheEntriesResponse::fromArray($response);
    }

    public function cacheTags(int $limit = 100, int $offset = 0, ?string $filter = null): CacheTagsResponse
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        if ($filter !== null) {
            $params['filter'] = $filter;
        }
        $endpoint = '/admin/cache/tags?' . http_build_query($params);
        $response = $this->request('GET', $endpoint);

        return CacheTagsResponse::fromArray($response);
    }

    public function purgePreview(string $pattern, string $type = 'url'): PurgePreviewResponse
    {
        $body = [
            'pattern' => $pattern,
            'type' => $type,
            'dry_run' => true,
        ];
        $response = $this->request('POST', '/admin/purge/preview', $body);

        return PurgePreviewResponse::fromArray($response);
    }

    // ========================================
    // Advanced Statistics
    // ========================================

    public function protectionStats(): ProtectionStatsResponse
    {
        $response = $this->request('GET', '/admin/stats/protection');

        return ProtectionStatsResponse::fromArray($response);
    }

    public function memoryStats(): MemoryStatsResponse
    {
        $response = $this->request('GET', '/admin/stats/memory');

        return MemoryStatsResponse::fromArray($response);
    }

    public function connections(): ConnectionsResponse
    {
        $response = $this->request('GET', '/admin/connections');

        return ConnectionsResponse::fromArray($response);
    }

    // ========================================
    // Cache Persistence
    // ========================================

    public function snapshot(?string $path = null): SnapshotResponse
    {
        $body = [];
        if ($path !== null) {
            $body['path'] = $path;
        }
        $response = $this->request('POST', '/admin/cache/snapshot', $body ?: null);

        return SnapshotResponse::fromArray($response);
    }

    // ========================================
    // DNS Discovery
    // ========================================

    public function discoveryList(): DiscoveryListResponse
    {
        $response = $this->request('GET', '/admin/discovery');

        return DiscoveryListResponse::fromArray($response);
    }

    public function discoveryDetail(string $name): DiscoveryDetailResponse
    {
        $endpoint = '/admin/discovery/' . urlencode($name);
        $response = $this->request('GET', $endpoint);

        return DiscoveryDetailResponse::fromArray($response);
    }

    public function discoveryRefresh(string $name): DiscoveryRefreshResponse
    {
        $endpoint = '/admin/discovery/' . urlencode($name) . '/refresh';
        $response = $this->request('POST', $endpoint);

        return DiscoveryRefreshResponse::fromArray($response);
    }

    // ========================================
    // Server-Sent Events (SSE)
    // ========================================

    public function events(): EventStream
    {
        return new EventStream(
            $this->baseUrl,
            $this->apiKey,
            $this->httpClient,
            $this->requestFactory
        );
    }

    // ========================================
    // HTTP Request Helper
    // ========================================

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     * @throws \Qoliber\Trident\Exception\TridentException
     */
    private function request(string $method, string $endpoint, ?array $body = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $this->logger->debug('Trident API request', [
            'method' => $method,
            'url' => $url,
            'body' => $body,
        ]);

        $request = $this->requestFactory->createRequest($method, $url);

        // Add Bearer token header if configured
        if ($this->apiKey !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
        }

        // Add body if present
        if ($body !== null) {
            $jsonBody = json_encode($body);
            if ($jsonBody === false) {
                throw new TridentException('Failed to encode request body as JSON');
            }

            $stream = $this->streamFactory->createStream($jsonBody);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            $this->logger->error('Trident API request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new TridentException(
                'Failed to connect to Trident: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        $this->lastStatusCode = $statusCode;
        $responseBody = (string) $response->getBody();

        $this->logger->debug('Trident API response', [
            'status' => $statusCode,
            'body' => $responseBody,
        ]);

        if ($statusCode >= 400) {
            throw new TridentException(
                sprintf('Trident API error (HTTP %d): %s', $statusCode, $responseBody),
                $statusCode
            );
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            // Some endpoints return empty body on success
            return ['success' => true, 'status_code' => $statusCode];
        }

        return $data;
    }
}
