# Trident PHP Library

Official PHP client library for Trident HTTP Cache Proxy.

## Requirements

- PHP 8.1 or higher
- PSR-7/PSR-17/PSR-18 compatible HTTP client (e.g., Guzzle)

## Installation

```bash
composer require qoliber/trident-php
```

## Quick Start

```php
use Qoliber\Trident\TridentFactory;

// Create from environment variables
$trident = TridentFactory::createFromEnv();

// Or create with explicit configuration
$trident = TridentFactory::create(
    adminUrl: 'http://localhost:9100',
    apiKey: 'your-admin-api-key'
);

// Check health
if ($trident->isHealthy()) {
    echo "Trident is running!";
}

// Get cache stats
$stats = $trident->stats();
echo "Hit ratio: {$stats->getHitRatioPercent()}%";
```

## Cache Purging

### Purge by Tag

```php
// Single tag
$trident->purgeTag('product.123');

// Multiple tags (OR - purge if ANY tag matches)
$trident->purgeTags(['product.1', 'product.2', 'product.3']);

// Multiple tags (AND - purge if ALL tags match)
$trident->purgeTagsAll(['category.electronics', 'featured']);

// Pattern matching (wildcard)
$trident->purgeTagPattern('product.*');

// Pattern matching (regex)
$trident->purgeTagPattern('product\.\d+', 'regex');
```

### Purge by URL

```php
// Hard purge (remove immediately)
$trident->purgeUrl('https://example.com/product/123');

// Soft purge (mark stale, serve while revalidating)
$trident->purgeUrl('https://example.com/product/123', soft: true);
```

### Purge All

```php
// Clear entire cache
$trident->purgeAll();
```

### Convenience Methods

```php
// E-commerce shortcuts
$trident->purgeProduct(123);
$trident->purgeCategory(45);
$trident->purgePage('home');

// Bulk purge patterns
$trident->purgeAllProducts();    // Purges product.*
$trident->purgeAllCategories();  // Purges category.*
```

## Cache Tags

### Tag Collection

```php
use Qoliber\Trident\Cache\TagCollection;

// Build a collection of tags
$tags = TagCollection::create()
    ->addProduct(123)
    ->addCategory(45)
    ->add('store.default')
    ->add('featured');

// Use in response headers
header('X-Cache-Tags: ' . $tags->toHeader());

// Or purge the collection
$trident->purgeCollection($tags);
```

### Tag Resolver

```php
use Qoliber\Trident\Cache\TagResolver;

$resolver = new TagResolver();

// Register custom resolver for your entities
$resolver->register('product', function ($product) {
    return [
        "product.{$product->getId()}",
        "category.{$product->getCategoryId()}",
        "store.{$product->getStoreId()}",
    ];
});

// Resolve tags from entities
$tags = $resolver->resolve('product', $product);
```

## Durable invalidation for platform modules (1.3.0)

Platform-neutral building blocks shared by the platform integrations
(WooCommerce today; Magento, Shopware, Sylius and PrestaShop next), so the
delivery contract is written — and tested — once.

| namespace | what |
|---|---|
| `Qoliber\Trident\Delivery` | X02/X03 delivery: `Purger` (record now with a grace period other drainers respect, deliver the process's own rows at the end of the request, remove only when acknowledged — safe for platforms that save without a transaction), `Drainer`, `Packer` (≤ 1000 tags per request), `Backoff` (1 s … 300 s, never gives up), `Acknowledgement` (HTTP 200 + int `purged` + string `mode`, optional `state` ≠ `refused`; or 202 + `state: recorded` in reflect mode), `Instances`/`Instance` (several Trident servers, each delivered and retried on its own), `PurgeClient` over a `Transport`, `Psr18Transport` |
| `Qoliber\Trident\Tags` | `TagSet`: bounded (Trident's 200-tag default, a header byte budget), prioritised (identity > reference > listed), normalised tags with an overflow tag |
| `Qoliber\Trident\Cache` | `Policy` / `Decision` / `RequestContext`: generic full-page cacheability rules; the platform passes its lists (state-changing query parameters, logged-in and session cookie prefixes) |
| `Qoliber\Trident\Esi` | `Markup::includeWithFallback()` (`<!--esi <esi:include/> --><esi:remove>inline</esi:remove>`, src validated), `FragmentUrl` (signed fragment URLs), `FragmentResponse::shared()`/`::private()` (headers: own TTL and tags / never stored), `TridentOrigin::matches()` (did the request come from a Trident address) |
| `Qoliber\Trident\Testing` | `InMemoryOutboxStore`, `FakeTransport` — test doubles other packages can reuse (they are in `src/` because Composer never loads a dependency's `autoload-dev`) |
| `assets/js/trident-sections.js` | the platform-neutral half of "private content from a local cache" (placeholders filled from `localStorage` under a version cookie); a platform binding configures it and serves the data endpoint |

A platform provides two adapters and wires them:

```php
use Qoliber\Trident\Delivery\{Instances, Purger, PurgeClient, Psr18Transport};

// 1. OutboxStore on the platform's own database layer, written through the SAME
//    connection the platform saves entities with. `record()` takes a due time
//    (the writer's grace) and returns the row id; `byIds()` returns rows by id.
//    Binary collation on `instance`. See WpdbOutboxStore in
//    integrations/ecommerce/woocommerce for a complete implementation.
$store = new MyDoctrineOutboxStore($connection);

// 2. Transport: Psr18Transport for Composer-native platforms, or your own
//    (WordPress HTTP API, Magento Curl).
$transport = new Psr18Transport($psr18Client, $requestFactory, $streamFactory);

[$instances, $errors] = Instances::parse($deploymentConfigList, $adminUrl, $adminToken);
$purger = new Purger(
    $store,
    $instances,
    fn ($i) => new PurgeClient($i, $transport),
    'soft',
    fn (callable $deliver) => $eventDispatcher->addListener('kernel.terminate', $deliver), // end of request
);

$purger->purgeTags(['product_42', 'category_7']);  // on save
$purger->drain(500);                               // from cron / a CLI command
$purger->drain(500, ignoreBackoff: true);          // an operator's "deliver now"
```

`TridentClient` purge methods now also say whether Trident acknowledged the
purge: `PurgeResponse::isAcknowledged()`, `->state`, `->failure`. The old
`isSuccess()` is unchanged (true for any purge body) and must not be used to
decide that a purge happened.

## Admin screens for platform modules (1.4.0)

Everything a platform's admin needs to show and drive Trident — the screen set
of the Magento module (dashboard, purge, cached pages, tags, coverage, warmer,
launch, reflect, denoisers, bans, backends, discovery, live events) — with no
per-platform API code.

| class | what |
|---|---|
| `Qoliber\Trident\Admin\Api` | the ONE request path to an instance's admin API: bearer token (only when set), JSON in and out, an empty body sent as `{}` (the engine rejects `[]`), errors mapped to `ApiError`, and one bounded retry when the admin rate limiter answers 429 with `retry_after_ms` (capped at 1 s). `TridentClient` and `PurgeClient` both use it |
| `Qoliber\Trident\Admin\ApiError` | status (0 = no response), the engine's error `code` (`REFLECT_DISABLED`, `LAUNCH_ACTIVE`, …), `isUnreachable()`, `isFeatureDisabled()` (a 404 `*_DISABLED` or a 503 "… not enabled": show it as information, not a failure), `reason()` (never the token) |
| `Qoliber\Trident\Admin\Fleet` | every configured instance (`Instances::parse()`): `each()` / `on($names)` run a call per instance and return one `InstanceResult` each — a dead instance is reported in its result, never thrown, so a screen still shows the live ones |
| `Qoliber\Trident\Admin\Payload` | a never-throwing, dotted-path view of wide or growing responses (warmer, reflect, denoisers, coverage, explain, variants) |
| `Qoliber\Trident\Admin\SiteUrl` | a storefront URL split the way the engine keys entries (path + host + scheme): the engine does not parse an absolute URL in `url`, and defaults the scheme to https |

```php
use Qoliber\Trident\Admin\Fleet;
use Qoliber\Trident\Client\TridentClient;

$fleet = new Fleet($instances, $transport);          // the same instances and transport the purges use
foreach ($fleet->each(fn (TridentClient $c) => $c->stats()) as $result) {
    echo $result->isOk()
        ? sprintf("%s: %.1f%% hits\n", $result->name(), $result->value->getHitRatioPercent())
        : sprintf("%s: %s\n", $result->name(), $result->reason());
}
$fleet->on(['edge-1'], fn (TridentClient $c) => $c->purgeUrl('https://shop.example/p/beanie/'));
```

`TridentClient::forInstance($instance, $transport)` builds a client over any
`Transport` (the WordPress HTTP API, Magento's Curl); new operator calls:
`status()`, `purgeHost()`, `purgeVary()`, `coverage()`, `cacheVariants()`,
`explain()`, `warmerStatus|Run|Cancel|Queue()`, `launch()`,
`reflectStatus|Enable|Disable|Queue()`, `denoiserReport()`,
`denoiserPathZones()`, `denoiserQueryScopes()`, `denoiserQuery|PathPin()`,
`…Unpin()`, `denoiserReset()`, `esiFragments()`, and
`EventStream::parseChunk()` for a bounded read of an SSE stream.

Corrected against the engine in the same release (each was checked on a live
1.8.0 Trident): the launch calls no longer append a launch id the engine does
not have; `memoryStats()` reads `GET /admin/memory`; `snapshot()` and the
discovery calls use the engine's paths; `purgePreview()` sends
`url_pattern`/`tag`/`tags`/`tag_pattern` and reads `would_free_bytes` and the
`sample`; `cacheEntries()`/`cacheTags()` gained the engine's `sort`/`tag`/`prefix`
filters; `CacheStatsResponse` now carries hits, misses, passes and the hit
ratio (it always reported 0 %); `LaunchResponse` reads the engine's `success`;
list and entry responses read `size`, `content_type`, `vary`, and discovery
reads `backend_name`/`addresses`. `purgeUrl()` splits an absolute URL into
path, host and scheme.

## 1.4.1 — fixes

- **Anything that is not the admin API is an error.** Every engine admin
  endpoint answers JSON. `Admin\Api::call()` used to turn a redirect (an
  `http://` API URL in front of an https-only listener, a wrong path) or a
  proxy's HTML page into `success: true`, so a screen could report "Launch
  started" or an empty "reachable" dashboard when Trident never saw the request.
  A 3xx, or a 2xx whose body is not JSON, now throws `ApiError`; `204 No
  Content` is still a success. Durable purge delivery (`Delivery\PurgeClient`,
  judged by `Acknowledgement`) is unchanged.
- **`purgeTagPattern()` sends `pattern_type`**, the field the engine reads. It
  sent `type`, which the engine ignored, so a regex tag pattern was purged as a
  wildcard.

## PSR-15 Middleware

### Cache Tag Middleware

```php
use Qoliber\Trident\Middleware\CacheTagMiddleware;

// Add to your middleware stack
$middleware = new CacheTagMiddleware(
    headerName: 'X-Cache-Tags',
    separator: ','
);

// In your request handlers, add tags:
CacheTagMiddleware::addTags($request, 'product.123', 'category.45');
```

### Cache Control Middleware

```php
use Qoliber\Trident\Middleware\CacheControlMiddleware;

$middleware = new CacheControlMiddleware(
    defaultTtl: 3600,       // 1 hour
    defaultSwr: 60,         // 60 seconds stale-while-revalidate
    defaultPrivate: false
);

// Disable caching for specific requests
$request = CacheControlMiddleware::disableCache($request);

// Set custom TTL
$request = CacheControlMiddleware::setTtl($request, 300);

// Mark as private (user-specific)
$request = CacheControlMiddleware::setPrivate($request);
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `TRIDENT_ADMIN_URL` | Trident admin API URL | `http://localhost:9100` |
| `TRIDENT_ADMIN_KEY` | Admin API authentication key | `null` |

## Testing

```bash
# Run unit tests
composer test

# Run with Docker (integration tests)
cd docker
docker-compose up -d
docker-compose exec php-app php /var/www/html/test-api.php
```

## License

MIT License - see LICENSE file for details.
