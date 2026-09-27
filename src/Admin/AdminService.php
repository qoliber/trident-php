<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Psr\Log\LoggerInterface;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Compatibility;
use Qoliber\Trident\Delivery\PurgeClient;
use Qoliber\Trident\Purge\PurgeRequest;
use Qoliber\Trident\Http\TransportFactory;

/**
 * The administration's Trident screens, over the library's Admin client and
 * Fleet: every read shows each configured instance separately (an instance
 * that is down or has a feature switched off is reported, never fatal), and
 * every action names the instances it ran on.
 *
 * Instances are addressed by NAME, from the configuration — the request never
 * supplies a URL, so nothing here can be pointed at another host.
 */
class AdminService
{
    /** Actions that change what Trident serves for everyone: the caller must confirm. */
    public const CONFIRM = [
        'purge_all', 'purge_pattern', 'purge_host',
        'launch_start', 'launch_complete', 'launch_abort',
        'reflect_enable', 'reflect_disable',
        'denoiser_reset', 'denoiser_zone_delete', 'denoiser_scope_delete',
        'ban_create', 'ban_delete', 'backend_drain',
    ];

    public const SCREENS = [
        'dashboard', 'entries', 'entry', 'tags', 'coverage', 'warmer', 'launch', 'reflect',
        'denoisers', 'bans', 'backends', 'discovery', 'events', 'purge',
    ];

    public const MAX_URLS = 100;

    private readonly \Closure $context;

    /**
     * @param callable(): AdminContext $context the platform's configuration for this request
     */
    public function __construct(
        callable $context,
        private readonly ShopAdapter $shop,
        private readonly TransportFactory $transports,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->context = \Closure::fromCallable($context);
    }

    private function ctx(): AdminContext
    {
        return ($this->context)();
    }

    /**
     * Settings without secrets, the outbox, and each instance's status.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $settings = $this->ctx();

        return [
            'settings' => $settings->view(),
            'outbox' => $settings->instances() !== [] ? $settings->outbox()->status() : null,
            'hosts' => $this->shopHosts(),
            'instances' => Export::results($this->fleet($settings)->each(static fn (TridentClient $c): array => self::verified($c))),
        ];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function screen(string $screen, array $query): array
    {
        if (!\in_array($screen, self::SCREENS, true)) {
            throw new AdminException(sprintf('Unknown screen "%s"', $screen));
        }
        $settings = $this->ctx();
        $fleet = $this->fleet($settings);
        $names = $this->targets($query, $fleet);
        $hosts = $this->shopHosts();

        // Nothing from an instance is shown until it has proven to be a Trident
        // admin API: a URL pointed at another internal service shows an error,
        // never that service's answers.
        $each = fn (callable $read): array => Export::results($fleet->on($names, static function (TridentClient $c, $i) use ($read) {
            self::verified($c);

            return $read($c, $i);
        }));

        return match ($screen) {
            'dashboard' => [
                'outbox' => $settings->instances() !== [] ? $settings->outbox()->status() : null,
                'results' => $each(static function (TridentClient $c): array {
                    // The instance is verified (above); its full answers are safe to read.
                    $stats = $c->stats()->raw();
                    $status = self::verified($c);
                    $health = Fleet::attempt(static fn () => $c->health()->raw());
                    $latency = Fleet::attempt(static fn () => $c->latencyStats()->raw());
                    $backends = Export::value(Fleet::attempt(static fn () => $c->backends()));
                    $l = is_array($latency) ? ($latency['latency'] ?? $latency) : [];

                    return [
                        'summary' => [
                            'version' => $status['version'],
                            // Lockstep versioning: a warning when the engine is on
                            // another MAJOR.MINOR than this integration.
                            'compatibility' => Compatibility::warning($status['version'])
                                ?? sprintf('OK — built for Trident %s.x', Compatibility::TRIDENT),
                            'license' => $status['license'],
                            'health' => is_array($health) ? ($health['status'] ?? null) : null,
                            'uptime_seconds' => is_array($health) ? ($health['uptime_seconds'] ?? null) : null,
                            'hit_ratio_percent' => $stats['hit_ratio'] ?? null,
                            'hits / misses / passes' => sprintf('%s / %s / %s', $stats['hits'] ?? '?', $stats['misses'] ?? '?', $stats['passes'] ?? '?'),
                            'entries' => $stats['entries'] ?? null,
                            'memory' => isset($stats['memory_used'], $stats['max_memory']) ? sprintf('%.1f MB of %.1f MB', $stats['memory_used'] / 1048576, $stats['max_memory'] / 1048576) : null,
                            'latency p50 / p95 / p99 (ms)' => is_array($l) ? sprintf('%s / %s / %s', $l['p50_ms'] ?? '?', $l['p95_ms'] ?? '?', $l['p99_ms'] ?? '?') : null,
                        ],
                        'backends' => is_array($backends) ? ($backends['backends'] ?? $backends) : null,
                        'stats' => $stats,
                    ];
                }),
            ],
            'entries' => ['results' => $each(static fn (TridentClient $c) => $c->cacheEntries(
                max(1, min(500, (int) ($query['limit'] ?? 100))),
                max(0, (int) ($query['offset'] ?? 0)),
                null,
                self::oneOf((string) ($query['sort'] ?? 'age'), ['age', 'hits', 'size'], 'age'),
                self::optional($query['tag'] ?? null),
            ))],
            'entry' => $this->entry((string) ($query['url'] ?? ''), $each),
            'tags' => ['results' => $each(static fn (TridentClient $c) => [
                'stats' => Fleet::attempt(static fn () => $c->tagStats()),
                'tags' => $c->cacheTags(
                    max(1, min(500, (int) ($query['limit'] ?? 100))),
                    0,
                    null,
                    self::oneOf((string) ($query['sort'] ?? 'count'), ['count', 'name'], 'count'),
                    self::optional($query['prefix'] ?? null),
                ),
            ])],
            'coverage' => $this->coverage($hosts, $each),
            'warmer' => ['results' => $each(static fn (TridentClient $c) => $c->warmerStatus())],
            'launch' => ['results' => $each(static fn (TridentClient $c) => $c->launchStatus())],
            'reflect' => ['results' => $each(static fn (TridentClient $c) => [
                'status' => $c->reflectStatus(),
                'queue' => Fleet::attempt(static fn () => $c->reflectQueue()),
            ])],
            'denoisers' => ['results' => $each(static function (TridentClient $c) use ($hosts) {
                $scopes = Fleet::attempt(static fn () => $c->denoiserQueryScopes()->raw());
                $export = Fleet::attempt(static fn () => $c->wafExport()->raw());

                return [
                    'report' => $c->denoiserReport(),
                    'zones' => Fleet::attempt(static fn () => $c->denoiserPathZones()),
                    'scopes' => $scopes,
                    'waf' => [
                        'format' => is_array($export) ? ($export['format'] ?? null) : null,
                        'dead_zones' => is_array($export) ? WafView::deadZones($export, $hosts) : [],
                        'noise' => is_array($scopes) ? WafView::noise(array_values((array) ($scopes['scopes'] ?? $scopes)), $hosts) : [],
                    ],
                ];
            })],
            'bans' => ['results' => $each(static fn (TridentClient $c) => $c->bans())],
            'backends' => ['results' => $each(static fn (TridentClient $c) => $c->backends())],
            'discovery' => ['results' => $each(static fn (TridentClient $c) => $c->discoveryList())],
            'events' => ['results' => $this->events($settings, $names)],
            'purge' => ['hosts' => $hosts, 'mode' => $settings->mode(), 'outbox' => $settings->instances() !== [] ? $settings->outbox()->status() : null],
        };
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function action(string $action, array $body): array
    {
        if (\in_array($action, self::CONFIRM, true) && ($body['confirm'] ?? false) !== true) {
            throw new AdminException(sprintf('"%s" changes what every visitor is served: confirm it (confirm: true)', $action));
        }
        $settings = $this->ctx();
        $fleet = $this->fleet($settings);
        $names = $this->targets($body, $fleet);
        $hosts = $this->shopHosts();
        $soft = self::mode($body, $settings) === 'soft';
        $on = fn (callable $call): array => ['results' => Export::results($fleet->on($names, static function (TridentClient $c, $i) use ($call) {
            self::verified($c);

            return $call($c, $i);
        }))];

        // Every input is read and validated HERE, before any instance is
        // called: a bad request is one 400, not an error per instance.
        $str = static fn (string $key): string => self::required($body, $key);
        $opt = static fn (string $key): ?string => self::optional($body[$key] ?? null);

        return match ($action) {
            'purge_urls' => (function () use ($on, $body, $hosts, $soft): array {
                // Absolute: the engine keys entries by host and scheme, and a
                // bare path would purge nothing on a multi-domain shop.
                $urls = $this->ownUrls($body['urls'] ?? [], $hosts, true);

                return $on(static fn (TridentClient $c) => $c->purgeUrls($urls, $soft));
            })(),
            'purge_tags' => $this->purgeTags((array) ($body['tags'] ?? []), $body, $settings->outbox()),
            'purge_entities' => $this->invalidate($str('kind'), (array) ($body['ids'] ?? [])),
            // The Shopware administration's names for the same action.
            'purge_products' => $this->invalidate('product', (array) ($body['ids'] ?? [])),
            'purge_categories' => $this->invalidate('category', (array) ($body['ids'] ?? [])),
            // The engine matches a pattern against the URL key of EVERY host on
            // the Trident; scoped to this shop's hosts here.
            'purge_pattern' => (static fn (string $pattern) => $on(static fn (TridentClient $c) => $c->purgeUrlPattern($pattern, $soft)))(self::hostScoped($str('pattern'), $hosts)),
            'purge_host' => (static fn (string $host) => $on(static fn (TridentClient $c) => $c->purgeHost($host, $soft)))(self::host($body, $hosts, false)),
            'purge_all' => $this->purgeAll(),
            'purge_preview' => (static fn (string $pattern) => $on(static fn (TridentClient $c) => $c->purgePreview($pattern)))($str('pattern')),
            'warmer_run' => $on(static fn (TridentClient $c) => $c->warmerRun()),
            'warmer_cancel' => $on(static fn (TridentClient $c) => $c->warmerCancel()),
            'warmer_queue_shop' => $this->warmShop(max(1, min(5000, (int) ($body['limit'] ?? 1000))), $names),
            'warmer_queue' => (function () use ($on, $body, $hosts): array {
                $urls = $this->ownUrls($body['urls'] ?? [], $hosts, true);

                return $on(static fn (TridentClient $c) => $c->warmerQueue($urls));
            })(),
            'launch_start' => (static fn (array $options) => $on(static fn (TridentClient $c) => $c->launchStart($options)))(array_filter(['reason' => $opt('reason')])),
            'launch_complete' => $on(static fn (TridentClient $c) => $c->launchComplete()),
            'launch_abort' => (static fn (?string $reason) => $on(static fn (TridentClient $c) => $c->launchAbort(null, $reason)))($opt('reason')),
            'reflect_enable' => (static fn (?string $level, ?string $duration, ?string $reason) => $on(static fn (TridentClient $c) => $c->reflectEnable($level, $duration, $reason)))(
                self::oneOfOptional($opt('level'), ['full', 'selective', 'ttl_extension'], 'level'),
                $opt('duration'),
                $opt('reason'),
            ),
            'reflect_disable' => (static fn (?string $mode) => $on(static fn (TridentClient $c) => $c->reflectDisable($mode)))(self::oneOfOptional($opt('mode'), ['replay', 'hard'], 'mode')),
            'denoiser_query_pin' => (static fn (string $param, string $class, string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserQueryPin($param, $class, $host, $prefix)))(
                $str('param'),
                self::oneOfOptional($str('class'), ['noise', 'signal'], 'class') ?? 'noise',
                self::host($body, $hosts, false, true),
                self::prefix($body),
            ),
            'denoiser_query_unpin' => (static fn (string $param, string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserQueryUnpin($param, $host, $prefix)))($str('param'), self::host($body, $hosts, true, true), self::prefix($body)),
            'denoiser_path_pin' => (static fn (string $status, string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserPathPin($status, $host, $prefix)))(
                self::oneOfOptional($str('status'), ['dead', 'alive'], 'status') ?? 'dead',
                self::host($body, $hosts, false, true),
                self::prefix($body),
            ),
            'denoiser_path_unpin' => (static fn (string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserPathUnpin($host, $prefix)))(self::host($body, $hosts), self::prefix($body)),
            'denoiser_zone_delete' => (static fn (string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserPathZoneDelete($host, $prefix)))(self::host($body, $hosts), self::prefix($body)),
            'denoiser_scope_delete' => (static fn (string $host, string $prefix) => $on(static fn (TridentClient $c) => $c->denoiserQueryScopeDelete($host, $prefix)))(self::host($body, $hosts), self::prefix($body)),
            'denoiser_reset' => (static fn (string $which) => $on(static fn (TridentClient $c) => $c->denoiserReset($which)))(self::oneOfOptional($str('which'), ['query', 'path', 'all'], 'which') ?? 'all'),
            'ban_create' => (static fn (string $pattern, string $type) => $on(static fn (TridentClient $c) => $c->createBan($pattern, $type)))(...(function () use ($str, $opt, $hosts): array {
                $type = self::oneOfOptional($opt('type'), ['url', 'tag', 'pattern'], 'type') ?? 'url';
                $pattern = $str('pattern');

                return match ($type) {
                    'url' => [$this->ownUrls([$pattern], $hosts, true)[0], 'url'],
                    'pattern' => [self::hostScoped($pattern, $hosts), 'pattern'],
                    default => [$pattern, 'tag'],
                };
            })()),
            'ban_delete' => (static fn (string $id) => $on(static fn (TridentClient $c) => $c->deleteBan($id)))($str('id')),
            'backend_drain' => (static fn (string $name) => $on(static fn (TridentClient $c) => $c->drainBackend($name)))($str('name')),
            'backend_restore' => (static fn (string $name) => $on(static fn (TridentClient $c) => $c->restoreBackend($name)))($str('name')),
            'discovery_refresh' => (static fn (string $name) => $on(static fn (TridentClient $c) => $c->discoveryRefresh($name)))($str('name')),
            'outbox_drain' => $this->drain((bool) ($body['force'] ?? false)),
            default => throw new AdminException(sprintf('Unknown action "%s"', $action)),
        };
    }

    /**
     * Queue this shop's pages — the home page and every canonical SEO URL
     * (products, categories, landing pages) — on Trident's warmer.
     *
     * the platform writes its sitemap gzip-compressed only (`sitemap/*.xml.gz`),
     * and the Trident 1.8 warmer does not decompress sitemap files: the
     * sitemap source yields no URLs. This is the working warm-up for a
     * the platform shop until it does.
     *
     * @param list<string> $names instances (empty = all)
     *
     * @return array<string, mixed>
     */
    public function warmShop(int $limit, array $names = []): array
    {
        $urls = $this->shop->catalogUrls($limit);
        $urls = array_slice(array_values(array_unique($urls)), 0, $limit);
        if ($urls === []) {
            throw new AdminException('This shop has no storefront URLs to warm');
        }
        $settings = $this->ctx();
        $results = [];
        foreach (array_chunk($urls, 500) as $chunk) {
            $results = Export::results($this->fleet($settings)->on($names, static fn (TridentClient $c) => $c->warmerQueue($chunk)));
        }

        return ['queued' => \count($urls), 'results' => $results];
    }

    /**
     * The instance's validated identity — or an error when what answers is
     * not a Trident admin API.
     *
     * @return array{version: string, license: string, mode: string}
     */
    public static function verified(TridentClient $client): array
    {
        $status = $client->status();
        $version = $status->string('version');
        $mode = $status->string('mode');
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}(?:[-+][0-9A-Za-z.]{1,20})?$/', $version) !== 1
            || !\in_array($mode, ['licensed', 'unlicensed', 'degraded', 'trial', 'expired', 'grace'], true)) {
            throw new \Qoliber\Trident\Exception\TridentException('Not a Trident admin API: nothing from this URL is shown');
        }

        return ['version' => $version, 'license' => \in_array($status->string('license'), ['valid', 'invalid', 'expired', 'missing', 'trial', 'grace'], true) ? $status->string('license') : 'unknown', 'mode' => $mode];
    }

    /**
     * This shop's storefront hosts as Trident keys them (`host[:port]`).
     *
     * @return list<string>
     */
    public function shopHosts(): array
    {
        return $this->shop->hosts();
    }

    private function fleet(AdminContext $settings): Fleet
    {
        return new Fleet($settings->instances(), $this->transports->transport(), $this->logger);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<string> empty = every instance
     */
    private function targets(array $input, Fleet $fleet): array
    {
        $name = self::optional($input['instance'] ?? null);
        if ($name === null || $name === '*') {
            return [];
        }
        if (!$fleet->has($name)) {
            throw new AdminException(sprintf('No instance named "%s"', $name));
        }

        return [$name];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $url, callable $each): array
    {
        if ($url === '') {
            throw new AdminException('url is required');
        }
        [$path] = $this->ownUrls([$url], $this->shopHosts());

        return ['url' => $path, 'results' => $each(static fn (TridentClient $c) => [
            'entry' => Fleet::attempt(static fn () => $c->cacheEntry($path)),
            'variants' => Fleet::attempt(static fn () => $c->cacheVariants($path)),
            'explain' => Fleet::attempt(static fn () => $c->explain($path)),
        ])];
    }

    /**
     * The shop's canonical SEO URLs (products, categories, landing pages):
     * which of them each instance has cached.
     *
     * @param list<string> $hosts
     *
     * @return array<string, mixed>
     */
    private function coverage(array $hosts, callable $each): array
    {
        $host = $hosts[0] ?? null;
        $paths = ['/'];
        foreach ($this->shop->catalogUrls(200) as $url) {
            $parts = parse_url($url);
            $urlHost = isset($parts['host']) ? strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '') : null;
            if ($urlHost === $host) {
                $paths[] = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }
        $scheme = $host !== null ? $this->shop->scheme($host) : null;

        return ['host' => $host, 'paths' => $paths, 'results' => $each(static fn (TridentClient $c) => $c->coverage(array_values(array_unique($paths)), $host, $scheme))];
    }

    /**
     * @param list<string> $names
     *
     * @return list<array<string, mixed>>
     */
    private function events(AdminContext $settings, array $names): array
    {
        $out = [];
        foreach ($settings->instances() as $instance) {
            if ($names !== [] && !\in_array($instance->name, $names, true)) {
                continue;
            }
            $poll = EventPoller::poll($this->transports, $instance, 2.0);
            $out[] = ['instance' => $instance->name, 'ok' => $poll['error'] === null, 'unreachable' => $poll['error'] !== null && $poll['status'] === 0, 'disabled' => false, 'error' => $poll['error'], 'data' => $poll['events']];
        }

        return $out;
    }

    /**
     * Tags go through the durable outbox, like a save: every instance, retried
     * until acknowledged. The shop-wide tag (`all`, on every page of this
     * shop) needs a confirmation — decided on the tags actually sent, after
     * prefixing and overflow expansion.
     *
     * @param list<mixed>          $tags
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function purgeTags(array $tags, array $body, PurgeOutbox $outbox): array
    {
        $trident = $outbox->purgeTags(array_map('strval', $tags));
        if ($trident === []) {
            throw new AdminException('tags is required');
        }
        if (\in_array($outbox->allTag(), $trident, true) && ($body['confirm'] ?? false) !== true) {
            throw new AdminException(sprintf('"%s" is on every page of this shop: confirm it (confirm: true)', $outbox->allTag()));
        }
        $outbox->recordTridentTags($trident);

        return $this->report($outbox->flush()) + ['tags' => $trident];
    }

    /**
     * Entities (products, taxons, …) are purged with the tags a save of them
     * would purge: through the durable outbox, for every instance, delivered
     * now.
     *
     * @param list<mixed> $ids
     *
     * @return array<string, mixed>
     */
    private function invalidate(string $kind, array $ids): array
    {
        if (!\array_key_exists($kind, $this->shop->entityKinds())) {
            throw new AdminException(sprintf('Unknown kind "%s"', $kind));
        }
        $ids = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $ids), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            throw new AdminException('ids is required');
        }
        try {
            $tags = $this->shop->entityTags($kind, $ids);
        } catch (\InvalidArgumentException $e) {
            throw new AdminException($e->getMessage());
        }
        $outbox = $this->ctx()->outbox();
        if ($this->shop instanceof EntityInvalidator) {
            // The platform's own invalidation (its caches too); it records
            // Trident's purge through the platform's durable path.
            $this->shop->invalidate($tags);
        } else {
            $outbox->recordTridentTags($outbox->purgeTags($tags));
        }

        return $this->report($outbox->flush()) + ['tags' => $outbox->purgeTags($tags)];
    }

    /**
     * @return array<string, mixed>
     */
    private function purgeAll(): array
    {
        $outbox = $this->ctx()->outbox();
        $outbox->recordAll();

        return $this->report($outbox->flush());
    }

    /**
     * @return array<string, mixed>
     */
    private function drain(bool $force): array
    {
        return $this->report($this->ctx()->outbox()->drain(1000, $force));
    }

    /**
     * @return array<string, mixed>
     */
    private function report(\Qoliber\Trident\Delivery\DrainReport $report): array
    {
        return [
            'delivered' => $report->delivered,
            'failed' => $report->failed,
            'purged' => $report->purged,
            'instances' => $report->instances,
            'outbox' => $this->ctx()->outbox()->status(),
        ];
    }

    /**
     * Paths of URLs on this shop. An absolute URL must name one of the shop's
     * storefront hosts: the screen purges this shop, not whatever a pasted
     * link points at.
     *
     * @param mixed        $urls
     * @param list<string> $hosts
     *
     * @return list<string>
     */
    private function ownUrls(mixed $urls, array $hosts, bool $absolute = false): array
    {
        $list = is_string($urls) ? preg_split('/\R/', $urls) : (array) $urls;
        $out = [];
        foreach ($list ?: [] as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $parts = parse_url($url);
            if ($parts === false) {
                throw new AdminException(sprintf('"%s" is not a URL', $url));
            }
            if (isset($parts['host'])) {
                $host = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
                if (!\in_array($host, $hosts, true)) {
                    throw new AdminException(sprintf('"%s" is not on this shop (%s)', $url, implode(', ', $hosts)));
                }
                $out[] = $absolute ? $url : ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            } else {
                $path = '/' . ltrim($url, '/');
                $out[] = $absolute && $hosts !== [] ? $this->absolute($hosts[0], $path) : $path;
            }
        }
        if ($out === []) {
            throw new AdminException('urls is required');
        }
        if (\count($out) > self::MAX_URLS) {
            throw new AdminException(sprintf('At most %d URLs at once', self::MAX_URLS));
        }

        return array_values(array_unique($out));
    }

    private function absolute(string $host, string $path): string
    {
        return $this->shop->scheme($host) . '://' . $host . $path;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function mode(array $body, AdminContext $settings): string
    {
        $mode = $body['mode'] ?? null;

        return \in_array($mode, ['soft', 'hard'], true) ? $mode : $settings->mode();
    }

    /**
     * The host a denoiser call is scoped to: this shop's own (the first
     * storefront host when none is given). A pin needs a real host — the
     * engine keys pins by the request's host, so a `*` pin never applies; only
     * unpin and forget take `*`, to clean such pins up.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $hosts
     */
    private static function host(array $body, array $hosts, bool $wildcard = true, bool $emptyIsShop = false): string
    {
        $host = strtolower(trim((string) ($body['host'] ?? '')));
        if ($host === '') {
            // Only a pin defaults to this shop; a purge or a delete must name
            // its host, never land on whichever domain happens to be first.
            if (!$emptyIsShop || \count($hosts) !== 1) {
                throw new AdminException(sprintf('host is required: one of this shop\'s hosts (%s)', implode(', ', $hosts)));
            }
            $host = $hosts[0];
        }
        if ($host === '*') {
            if (!$wildcard) {
                throw new AdminException('A pin needs one of this shop\'s hosts: Trident keys pins by the request host and has no wildcard');
            }

            return $host;
        }
        if (!\in_array($host, $hosts, true)) {
            throw new AdminException(sprintf('"%s" is not one of this shop\'s hosts (%s)', $host, implode(', ', $hosts)));
        }

        return $host;
    }

    /**
     * A URL-path regex, anchored to this shop's hosts: the engine's URL key is
     * `METHOD:scheme:host:path`, so `^/sale/` becomes
     * `^[A-Z]+:[a-z]+:(?:shop\.example|www\.shop\.example):/sale/`.
     *
     * @param list<string> $hosts
     */
    public static function hostScoped(string $pathPattern, array $hosts): string
    {
        if ($hosts === []) {
            throw new AdminException('This shop has no hosts to scope the pattern to');
        }
        if (@preg_match('#' . str_replace('#', '\\#', $pathPattern) . '#', '') === false) {
            throw new AdminException('pattern is not a valid regular expression');
        }
        $path = ltrim($pathPattern, '^');
        if ($path === '' || $path[0] !== '/') {
            $path = '/.*' . $path;
        }

        // Only true metacharacters are escaped: the engine compiles the pattern
        // with Rust's `regex`, which rejects needless escapes such as `\:`.
        $quote = static fn (string $h): string => (string) preg_replace('/[.\\\\+*?()|\[\]{}^$]/', '\\\\$0', $h);

        return '^[A-Z]+:[a-z]+:(?:' . implode('|', array_map($quote, $hosts)) . '):' . $path;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function prefix(array $body): string
    {
        $prefix = trim((string) ($body['path_prefix'] ?? '/'));

        return str_starts_with($prefix, '/') ? $prefix : '/' . $prefix;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function required(array $body, string $key): string
    {
        $value = trim((string) ($body[$key] ?? ''));
        if ($value === '') {
            throw new AdminException(sprintf('%s is required', $key));
        }

        return $value;
    }

    private static function optional(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOfOptional(?string $value, array $allowed, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!\in_array($value, $allowed, true)) {
            throw new AdminException(sprintf('%s must be one of %s', $field, implode(', ', $allowed)));
        }

        return $value;
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(string $value, array $allowed, string $default): string
    {
        return \in_array($value, $allowed, true) ? $value : $default;
    }
}
