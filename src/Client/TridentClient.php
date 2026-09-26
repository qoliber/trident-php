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
use Qoliber\Trident\Admin\Api;
use Qoliber\Trident\Admin\Payload;
use Qoliber\Trident\Admin\SiteUrl;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\Instances;
use Qoliber\Trident\Delivery\Psr18Transport;
use Qoliber\Trident\Delivery\Transport;
use Qoliber\Trident\Delivery\Acknowledgement;
use Qoliber\Trident\Exception\InvalidRequest;
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
use Qoliber\Trident\Response\ClearResponse;
use Qoliber\Trident\Purge\PurgeRequest;
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
    private ?ClientInterface $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private LoggerInterface $logger;
    private Api $api;

    /** HTTP status of the last response, for acknowledgement checks. */
    private ?int $lastStatusCode = null;

    /**
     * Over a PSR-18 client (with its factories), or over any
     * {@see Transport} — the WordPress HTTP API, Magento's client — in which
     * case the factories are not needed.
     */
    public function __construct(
        string $baseUrl,
        ?string $apiKey,
        ClientInterface|Transport $httpClient,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        string $instanceName = Instances::DEFAULT_NAME
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new NullLogger();
        if ($httpClient instanceof ClientInterface) {
            if ($requestFactory === null || $streamFactory === null) {
                throw new \InvalidArgumentException('A PSR-18 client needs its request and stream factories.');
            }
            $this->httpClient = $httpClient;
            $this->requestFactory = $requestFactory;
            $transport = new Psr18Transport($httpClient, $requestFactory, $streamFactory);
        } else {
            $transport = $httpClient;
        }
        $this->api = new Api(new Instance($instanceName, $this->baseUrl, (string) $apiKey), $transport, $this->logger);
    }

    /**
     * A client for one configured instance over a transport.
     */
    public static function forInstance(Instance $instance, Transport $transport, ?LoggerInterface $logger = null): self
    {
        return new self(
            $instance->apiUrl,
            $instance->apiToken === '' ? null : $instance->apiToken,
            $transport,
            null,
            null,
            $logger,
            $instance->name
        );
    }

    public function instance(): Instance
    {
        return $this->api->instance();
    }

    /** HTTP status of the most recent response. */
    public function lastStatusCode(): ?int
    {
        return $this->lastStatusCode;
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
        // PurgeRequest builds the engine body: path + host + scheme (the
        // engine keys entries on those separately and does not parse a URL in
        // `url`) and an explicit mode (without it the engine applies its
        // admin.default_purge_mode, so "soft" could silently be hard).
        $request = PurgeRequest::url($url);

        return $this->purge($soft ? $request->soft() : $request->hard());
    }

    public function purgeUrls(array $urls, bool $soft = false): PurgeResponse
    {
        // The engine's bulk endpoint takes PATHS plus one `host` and `scheme`
        // (defaulting to its own listener) and does not parse a URL in `urls`:
        // absolute URLs sent as-is purged nothing. They are grouped by host
        // and scheme, one request per group; paths go as before. The mode is
        // always explicit: without it the engine applies its
        // admin.default_purge_mode, so "soft" could silently be hard.
        $mode = $soft ? 'soft' : 'hard';
        $groups = [];
        foreach ($urls as $url) {
            $url = (string) $url;
            if (preg_match('#^https?://#i', $url) === 1) {
                $site = \Qoliber\Trident\Admin\SiteUrl::parse($url);
                $groups[(string) $site->scheme . '://' . (string) $site->host][] = $site->path;
            } else {
                $groups[''][] = $url;
            }
        }
        if ($groups === []) {
            $groups[''] = [];
        }

        // Each group is its own purge and is judged on its own (the bulk
        // answer has `total_purged`, not `purged`): the merged result is
        // acknowledged only when EVERY group was — a 200 with the schema or a
        // 202 `recorded` — so a caller never drops a purge one origin refused.
        $purged = 0;
        $results = [];
        $failures = [];
        $unacked = [];
        $lastError = null;
        $done = [];
        $deferred = false;
        foreach ($groups as $origin => $paths) {
            $label = $origin === '' ? 'paths' : (string) $origin;
            $body = ['urls' => array_values($paths)];
            if ($origin !== '') {
                [$scheme, $host] = explode('://', (string) $origin, 2);
                $body['host'] = $host;
                $body['scheme'] = $scheme;
            }
            $body['mode'] = $mode;
            try {
                $answer = $this->request('POST', '/admin/purge/urls', $body);
            } catch (TridentException $e) {
                $failures[] = sprintf('%s: %s', $label, $e->getMessage());
                $lastError = $e;
                continue;
            }
            $status = (int) $this->lastStatusCode;
            $judged = $answer;
            if (is_int($answer['total_purged'] ?? null)) {
                $judged['purged'] = $answer['total_purged'];
            }
            // Counted whether or not the group is acknowledged. Count: the engine's total_purged; an older answer's int `purged`.
            $purged += is_int($answer['total_purged'] ?? null) ? $answer['total_purged'] : (is_int($answer['purged'] ?? null) ? $answer['purged'] : 0);
            $failure = Acknowledgement::purgeFailure($status, (string) json_encode($judged));
            $done[] = $label;
            if ($failure !== null) {
                // Answered, but not an acknowledgement: not a failed request
                // (1.5 reported it as a success) — just not acknowledged.
                $unacked[] = sprintf('%s: %s', $label, $failure);
                continue;
            }
            $deferred = $deferred || $status === 202;
            $results = array_merge($results, (array) ($answer['results'] ?? []));
        }

        $merged = ['total_purged' => $purged, 'results' => $results, 'mode' => $mode];
        if ($failures === [] && $unacked === []) {
            $merged['purged'] = $purged;
            if ($deferred) {
                $merged['state'] = 'recorded';
            }
            return PurgeResponse::fromArray($merged, 200);
        }
        if ($failures === []) {
            // Every request answered; some not with an acknowledgement.
            $merged['purged_count'] = $purged;
            $merged['error'] = 'not acknowledged on ' . implode('; ', $unacked);
            return PurgeResponse::fromArray($merged, 200);
        }
        // No group purged: an error, as in 1.5 (which threw on any failure).
        if ($done === []) {
            if ($lastError !== null && count($failures) === 1) {
                throw $lastError;
            }
            throw new TridentException('Purge failed on every host: ' . implode('; ', $failures));
        }
        // Some purged, some did not: neither a success nor acknowledged, with
        // the failed groups named.
        $merged['failed_groups'] = $failures;
        return PurgeResponse::partial($purged, $mode, sprintf('purged on %s; failed on %s', implode(', ', $done), implode('; ', array_merge($failures, $unacked))), $merged);
    }

    public function purgeUrlPattern(string $pattern, bool $soft = false): PurgeResponse
    {
        $body = [
            'pattern' => $pattern,
        ];

        // Always explicit: without it the engine applies its
        // admin.default_purge_mode, so "soft" could silently be hard.
        $body['mode'] = $soft ? 'soft' : 'hard';

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
            // The engine's field is `pattern_type`; an unknown `type` was
            // ignored, so every regex was purged as a wildcard.
            'pattern_type' => $patternType,
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

    /**
     * Send a purge exactly as built (1.5.0) — the one purge call where every
     * option the engine has is available: an explicit soft/hard mode on every
     * kind (tag, tags and tag-pattern purges included, which the older
     * methods leave to the engine's admin.default_purge_mode), `exclude_tags`
     * and the match mode for tag purges, `pattern_type` for tag patterns.
     *
     *     $client->purge(PurgeRequest::tags(['cat_5'])->excluding(['home'])->soft());
     *
     * A full clear (PurgeRequest::all()) is answered with the clear schema;
     * see clearCache() for a typed result. A ban is not a purge: createBan().
     */
    public function purge(PurgeRequest $request): PurgeResponse
    {
        $response = $this->request('POST', $request->engineEndpoint(), $request->toEngineBody());

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    /**
     * Remove every entry on this instance (1.5.0), with the engine's clear
     * schema typed: whether it cleared, how many entries, how many bytes. A
     * clear has no soft/hard mode.
     */
    public function clearCache(): ClearResponse
    {
        $response = $this->request('POST', '/admin/cache/clear', ['confirm' => true]);

        return ClearResponse::fromArray($response, $this->lastStatusCode);
    }

    public function purgeHash(string $hash, bool $soft = false): PurgeResponse
    {
        $body = [
            'hash' => $hash,
        ];

        // Always explicit: without it the engine applies its
        // admin.default_purge_mode, so "soft" could silently be hard.
        $body['mode'] = $soft ? 'soft' : 'hard';

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

    /**
     * @param string|null $launchId Ignored: the engine runs one launch at a time
     *                              and addresses it without an id. Kept for BC.
     */
    public function launchStatus(?string $launchId = null): LaunchStatusResponse
    {
        $response = $this->request('GET', '/admin/launch/status');

        return LaunchStatusResponse::fromArray($response);
    }

    /**
     * @param string|null $launchId Ignored (one launch at a time). Kept for BC.
     */
    public function launchComplete(?string $launchId = null): LaunchResponse
    {
        $response = $this->request('POST', '/admin/launch/complete');

        return LaunchResponse::fromArray($response);
    }

    /**
     * @param string|null $launchId Ignored (one launch at a time). Kept for BC.
     */
    public function launchAbort(?string $launchId = null, ?string $reason = null): LaunchResponse
    {
        $body = [];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        $response = $this->request('POST', '/admin/launch/abort', $body);

        return LaunchResponse::fromArray($response);
    }

    // ========================================
    // Cache Browsing
    // ========================================

    /**
     * @param string|null $filter Not supported by the engine (sent, ignored). Kept for BC.
     * @param string|null $sort   `age` (default) | `hits` | `size`.
     * @param string|null $tag    Only entries carrying this tag.
     */
    public function cacheEntries(
        int $limit = 100,
        int $offset = 0,
        ?string $filter = null,
        ?string $sort = null,
        ?string $tag = null
    ): CacheEntriesResponse {
        $response = $this->request('GET', '/admin/cache/entries', null, [
            'limit' => $limit,
            'offset' => $offset,
            'filter' => $filter,
            'sort' => $sort,
            'tag' => $tag,
        ]);

        return CacheEntriesResponse::fromArray($response);
    }

    /**
     * @param string|null $filter Not supported by the engine (sent, ignored). Kept for BC.
     * @param string|null $sort   `count` (default) | `name`.
     * @param string|null $prefix Only tags starting with this.
     */
    public function cacheTags(
        int $limit = 100,
        int $offset = 0,
        ?string $filter = null,
        ?string $sort = null,
        ?string $prefix = null
    ): CacheTagsResponse {
        $response = $this->request('GET', '/admin/cache/tags', null, [
            'limit' => $limit,
            'offset' => $offset,
            'filter' => $filter,
            'sort' => $sort,
            'prefix' => $prefix,
        ]);

        return CacheTagsResponse::fromArray($response);
    }

    public function purgePreview(string $pattern, string $type = 'url'): PurgePreviewResponse
    {
        // The engine takes one selector field per kind; `tags` is a
        // comma-separated list here.
        $body = match ($type) {
            'tag' => ['tag' => $pattern],
            'tags' => ['tags' => array_values(array_filter(
                array_map('trim', explode(',', $pattern)),
                static fn (string $tag): bool => $tag !== ''
            ))],
            'tag_pattern' => ['tag_pattern' => $pattern],
            default => ['url_pattern' => $pattern],
        };
        $body['sample_limit'] = 20;
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
        $response = $this->request('GET', '/admin/memory');

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
        $response = $this->request('POST', '/admin/snapshot', $body ?: null);

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
        $response = $this->request('GET', '/admin/discovery/detail', null, ['name' => $name]);

        return DiscoveryDetailResponse::fromArray($response);
    }

    public function discoveryRefresh(string $name): DiscoveryRefreshResponse
    {
        $response = $this->request('POST', '/admin/discovery/refresh', null, ['name' => $name]);

        return DiscoveryRefreshResponse::fromArray($response);
    }

    // ========================================
    // Operator screens (status, purge by host/vary, coverage, explain,
    // warmer, launch, reflect, denoisers, ESI)
    // ========================================

    public function status(): Payload
    {
        return new Payload($this->request('GET', '/admin/status'));
    }

    public function purgeHost(string $host, bool $soft = false): PurgeResponse
    {
        $response = $this->request('POST', '/admin/purge/host', ['host' => $host, 'mode' => $soft ? 'soft' : 'hard']);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    /**
     * Purge every entry stored under one value of a Vary dimension
     * (`accept-encoding` / `gzip`, `cookie:x-magento-vary` / `group1`).
     */
    public function purgeVary(string $header, string $value, bool $soft = false): PurgeResponse
    {
        $response = $this->request('POST', '/admin/purge/vary', [
            'header' => $header,
            'value' => $value,
            'mode' => $soft ? 'soft' : 'hard',
        ]);

        return PurgeResponse::fromArray($response, $this->lastStatusCode);
    }

    /**
     * Which of these pages are cached. Paths are looked up under `$host` and
     * `$scheme` (the engine defaults the scheme to https).
     *
     * @param list<string> $paths
     */
    public function coverage(array $paths, ?string $host = null, ?string $scheme = null, ?string $method = null): Payload
    {
        $body = ['urls' => array_values($paths)];
        if ($host !== null) {
            $body['host'] = $host;
        }
        if ($scheme !== null) {
            $body['scheme'] = $scheme;
        }
        // The engine checks GET entries unless told otherwise
        // (CacheCoverageRequest.method): a HEAD-only warm-up is another key.
        if ($method !== null && $method !== '') {
            $body['method'] = strtoupper($method);
        }
        return new Payload($this->request('POST', '/admin/cache/coverage', $body));
    }

    /**
     * Every stored variant of a page and the Vary axes it splits on.
     */
    public function cacheVariants(string $url): Payload
    {
        // Absolute: the engine parses the scheme out of an absolute URL but
        // ignores a separate `scheme` field here (defaulting to http).
        return new Payload($this->request('GET', '/admin/cache/variants', null, ['url' => SiteUrl::parse($url)->absolute()]));
    }

    /**
     * Would this request be cached, and is it now — without sending it.
     *
     * The engine evaluates explain without TLS context: its in-cache part
     * (`verdict` hit/miss, `entry`) is looked up under the http key. For an
     * https page only `cacheable` / `reason` are meaningful.
     */
    public function explain(string $url, string $method = 'GET', bool $detail = false): Payload
    {
        return $this->explainRequest($url, $method, [], [], $detail);
    }

    /**
     * Ask the engine how it would treat a request (1.5.0): the full request
     * context `/admin/explain` accepts — method, URL, any request headers,
     * and cookies (sent as the `Cookie` header, the way a browser would).
     *
     * The URL's host becomes the `Host` header unless `$headers` sets one.
     * The scheme is not sent: the engine has no field for it and evaluates
     * explain without TLS context, so for an https page only `cacheable` and
     * `reason` are meaningful; `verdict` and `entry` describe the http key.
     *
     * @param array<string, string> $headers Request headers, any case.
     * @param array<string, string> $cookies Cookie name => value.
     */
    public function explainRequest(
        string $url,
        string $method = 'GET',
        array $headers = [],
        array $cookies = [],
        bool $detail = false
    ): Payload {
        $page = SiteUrl::parse($url);
        $sent = [];
        foreach ($headers as $name => $value) {
            $sent[strtolower((string) $name)] = (string) $value;
        }
        if ($page->host !== null && !isset($sent['host'])) {
            $sent['host'] = $page->host;
        }
        if ($cookies !== []) {
            $pairs = [];
            foreach ($cookies as $name => $value) {
                // A `;` or `,` would add another cookie, a control character
                // another header: refused, never silently rewritten.
                $name = (string) $name;
                $value = (string) $value;
                if ($name === '' || preg_match('/[;,=\s\x00-\x1f\x7f]/', $name) === 1
                    || preg_match('/[;,\x00-\x1f\x7f]/', $value) === 1) {
                    throw new InvalidRequest(sprintf(
                        'Cookie %s: a name or value with ";", "," or a control character (or a name with "=" '
                        . 'or whitespace) would inject another cookie; it was not sent.',
                        json_encode($name)
                    ));
                }
                $pairs[] = $name . '=' . $value;
            }
            $sent['cookie'] = implode('; ', array_filter(
                [$sent['cookie'] ?? '', implode('; ', $pairs)],
                static fn (string $part): bool => $part !== ''
            ));
        }
        $body = ['method' => strtoupper($method), 'url' => $page->path, 'detail' => $detail];
        if ($sent !== []) {
            $body['headers'] = $sent;
        }
        return new Payload($this->request('POST', '/admin/explain', $body));
    }

    public function warmerStatus(): Payload
    {
        return new Payload($this->request('GET', '/admin/warmer/status'));
    }

    /** Warm every configured source now (202 `started`, `queued`). */
    public function warmerRun(): Payload
    {
        return new Payload($this->request('POST', '/admin/warmer/run'));
    }

    public function warmerCancel(): Payload
    {
        return new Payload($this->request('POST', '/admin/warmer/cancel'));
    }

    /**
     * @param list<string> $urls Absolute URLs to warm.
     */
    public function warmerQueue(array $urls): Payload
    {
        return new Payload($this->request('POST', '/admin/warmer/queue', ['urls' => array_values($urls)]));
    }

    /** Launch state without a launch id (`active`, `state`, progress). */
    public function launch(): Payload
    {
        return new Payload($this->request('GET', '/admin/launch/status'));
    }

    public function reflectStatus(): Payload
    {
        return new Payload($this->request('GET', '/admin/reflect/status'));
    }

    /**
     * @param string|null $level    `full` | `selective` | `ttl_extension`; null = the configured default.
     * @param string|null $duration Humantime (`30m`); null = until disabled (capped by config).
     */
    public function reflectEnable(?string $level = null, ?string $duration = null, ?string $reason = null): Payload
    {
        $body = array_filter(['level' => $level, 'duration' => $duration, 'reason' => $reason], static fn ($v): bool => $v !== null && $v !== '');
        return new Payload($this->request('POST', '/admin/reflect/enable', $body));
    }

    /**
     * @param string|null $mode `replay` (default: run deferred purges) | `hard`.
     */
    public function reflectDisable(?string $mode = null): Payload
    {
        return new Payload($this->request('POST', '/admin/reflect/disable', $mode !== null ? ['mode' => $mode] : []));
    }

    public function reflectQueue(): Payload
    {
        return new Payload($this->request('GET', '/admin/reflect/queue'));
    }

    public function denoiserReport(): Payload
    {
        return new Payload($this->request('GET', '/admin/denoisers/report'));
    }

    public function denoiserPathZones(): Payload
    {
        return new Payload($this->request('GET', '/admin/denoisers/path/zones'));
    }

    public function denoiserQueryScopes(): Payload
    {
        return new Payload($this->request('GET', '/admin/denoisers/query/scopes'));
    }

    /**
     * @param string $class `noise` | `signal`.
     * @param string $host  The site's host (`host[:port]`); `*` or empty is refused (see pinHost()).
     */
    public function denoiserQueryPin(string $param, string $class, string $host = '*', string $pathPrefix = '/'): Payload
    {
        self::oneOf('class', $class, ['noise', 'signal']);
        self::required('param', $param);
        $host = self::pinHost($host);
        return new Payload($this->request('POST', '/admin/denoisers/query/pin', [
            'param' => $param,
            'class' => $class,
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    public function denoiserQueryUnpin(string $param, string $host = '*', string $pathPrefix = '/'): Payload
    {
        return new Payload($this->request('POST', '/admin/denoisers/query/unpin', [
            'param' => $param,
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    /**
     * @param string $zoneStatus `dead` | `alive`.
     * @param string $host       The site's host (`host[:port]`); `*` or empty is refused (see pinHost()).
     */
    public function denoiserPathPin(string $zoneStatus, string $host = '*', string $pathPrefix = '/'): Payload
    {
        self::oneOf('status', $zoneStatus, ['dead', 'alive']);
        $host = self::pinHost($host);
        return new Payload($this->request('POST', '/admin/denoisers/path/pin', [
            'status' => $zoneStatus,
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    public function denoiserPathUnpin(string $host = '*', string $pathPrefix = '/'): Payload
    {
        return new Payload($this->request('POST', '/admin/denoisers/path/unpin', [
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    /**
     * Forget one learned path zone entirely (1.5.0); 404 when there is none.
     */
    public function denoiserPathZoneDelete(string $host = '*', string $pathPrefix = '/'): Payload
    {
        return new Payload($this->request('DELETE', '/admin/denoisers/path/zone', [
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    /**
     * Forget one learned query scope entirely (1.5.0).
     */
    public function denoiserQueryScopeDelete(string $host = '*', string $pathPrefix = '/'): Payload
    {
        return new Payload($this->request('DELETE', '/admin/denoisers/query/scope', [
            'host' => $host,
            'path_prefix' => $pathPrefix,
        ]));
    }

    /**
     * What the denoisers learned, as the portable `trident-waf-v1` export
     * (1.5.0): noise query parameters and dead path zones.
     */
    public function wafExport(): Payload
    {
        return new Payload($this->request('GET', '/admin/denoisers/export/waf'));
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(string $field, string $value, array $allowed): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidRequest(sprintf(
                'Denoiser %s must be %s; got "%s".',
                $field,
                '"' . implode('" or "', $allowed) . '"',
                $value
            ));
        }
    }

    /**
     * A pin applies to one real host. The engine keys learned scopes and zones
     * as `{host}|{path_prefix}` with the REQUEST's host and has no `*`
     * fallback, so a pin on `*` (or on no host) is accepted and never used.
     * Unpin and delete still take `*`, to clean such pins up.
     */
    private static function pinHost(string $host): string
    {
        // Sent as the engine keys it: query scopes hash the host verbatim,
        // path zones lowercase it — trimmed and lowercased matches both.
        $host = strtolower(trim($host));
        if ($host === '' || $host === '*') {
            throw new InvalidRequest(sprintf(
                'Denoiser pins need the site\'s host (host[:port]); "%s" matches no request — the engine keys pins by the request host and has no wildcard.',
                $host
            ));
        }

        return $host;
    }

    private static function required(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidRequest(sprintf('Denoiser %s must not be empty.', $field));
        }
    }

    /**
     * Forget what a denoiser learned.
     *
     * @param string $which `query` | `path`.
     */
    public function denoiserReset(string $which): Payload
    {
        if (!in_array($which, ['query', 'path'], true)) {
            throw new \InvalidArgumentException('Denoiser must be "query" or "path".');
        }
        return new Payload($this->request('POST', '/admin/denoisers/' . $which . '/reset'));
    }

    /** ESI fragments the engine has seen, with their state and last failure. */
    public function esiFragments(): Payload
    {
        return new Payload($this->request('GET', '/admin/esi/fragments'));
    }

    // ========================================
    // Server-Sent Events (SSE)
    // ========================================

    /**
     * The live SSE stream. Needs the PSR-18 client (it reads the response as
     * it arrives); a transport-built client reads a bounded burst with
     * {@see \Qoliber\Trident\Events\EventStream::parseChunk()} instead.
     */
    public function events(): EventStream
    {
        if ($this->httpClient === null || $this->requestFactory === null) {
            throw new TridentException('events() needs a PSR-18 client; this client was built over a Transport.');
        }
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
     * @param array<string, mixed>|null        $body
     * @param array<string, scalar|null>        $query
     * @return array<string, mixed>
     * @throws \Qoliber\Trident\Exception\TridentException
     */
    private function request(string $method, string $endpoint, ?array $body = null, array $query = []): array
    {
        $reply = $this->api->call($method, $endpoint, $query, $body);
        $this->lastStatusCode = $reply['status'];
        return $reply['data'];
    }
}
