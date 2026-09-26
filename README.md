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

## 1.5.0 — a complete admin client

Everything a platform's admin screens need is now on the typed client; the
lower-level `Admin\Api` is for what the library does not cover yet. Nothing is
removed or renamed: 1.4.x code runs unchanged (the 1.4.1 test suite passes
as-is).

- **Every typed response keeps the engine's full answer.** `raw()` returns the
  decoded JSON it was built from, `payload()` the same answer as a dot-path
  `Payload` (`$stats->payload()->int('hits')`). `Payload::raw()` is the same
  for the untyped reads. `toArray()` keeps its 1.4 shape — use `raw()` for
  the complete answer.
- **Fields the engine sends are no longer dropped or read under the wrong
  name** (checked against a live 1.8.0 engine; the old names stay as
  fallbacks): health uptime (`uptime_seconds`), compressed entries
  (`current_compressed_entries`), `compressionsTotal`, `tagIndexedKeys`,
  `urlIndexedKeys`, protection `enabled` (`protection_enabled`) with the
  `total*` counters and per-backend queue figures (`backends` stays keyed by
  backend name — the 1.4.1 shape — and each row carries its `name`),
  memory `footprintPercentiles`, launch `active`, connection `queued`
  (`waitingRequests`), `totalCreated`, `totalReused`, latency `sampleCount`.
- **`purge(PurgeRequest)`** sends a purge with every option the engine has:
  soft/hard on every kind (tag, tags and tag pattern included), `exclude_tags`
  and the match mode for tags, `pattern_type` for tag patterns. **The mode is
  sent only when you choose one** (`->soft()` or `->hard()`); a request without
  either leaves it to the operator's `admin.default_purge_mode`, whose engine
  default is **soft**. `getMode()` still answers `hard` for such a request (its
  1.4.1 answer) — `hasExplicitMode()` says whether a mode is sent. `purgeUrl()`
  keeps sending its explicit mode, as in 1.4.1. `PurgeRequest` gained `hard()`,
  `excluding()`, `hasExplicitMode()`, `engineEndpoint()` and `toEngineBody()`.
- **`clearCache(): ClearResponse`** — a full clear typed on the engine's clear
  schema (`cleared`, `entriesRemoved`, `bytesFreed`, `isAcknowledged()`). A
  clear has no soft/hard mode. `purgeAll()` is unchanged.
- **Delivery reports what was purged.** `PurgeAttempt` carries `purged` and
  `state` (a deferred 202 purge has purged nothing yet: `purged` is null);
  `DrainReport::$purged` and each instance's `purged` add them up.
  `PurgeClient::clear()` clears an instance, judged by the clear schema.
- **`explainRequest($url, $method, $headers, $cookies, $detail)`** — the full
  request context `/admin/explain` accepts; cookies go in the `Cookie`
  header, the URL's host becomes `Host` unless given. A cookie name or value
  that would inject another cookie or header (`;`, `,`, control characters; a
  name with `=` or whitespace) is refused, not rewritten. The scheme is not sent:
  the engine evaluates explain without TLS context, so for an https page only
  `cacheable` and `reason` are meaningful.
- **`Exception\InvalidRequest`** (a `TridentException`) — a request the client
  refuses to send: an invalid denoiser pin, an injecting cookie. Code that
  catches `TridentException` for admin failures, as 1.4.x code does, still
  catches it; nothing reaches Trident.
- **Denoisers:** pins are validated before anything is sent (`class`
  `noise`|`signal`, `status` `dead`|`alive`, a non-empty parameter);
  `denoiserPathZoneDelete()`, `denoiserQueryScopeDelete()` and `wafExport()`
  (the `trident-waf-v1` export) are new.

**`TridentClientInterface` is frozen for 1.x.** The new methods (`purge()`,
`clearCache()`, `explainRequest()`, `wafExport()`, the denoiser deletes) are on
the `TridentClient` class only: adding methods to the interface would break
every class that implements it. Type against `TridentClient` to use them.

### Which layer to use (platform authors)

| you need | use |
|---|---|
| purges that must not be lost (a product save) | `Delivery\Purger` + your `OutboxStore` — recorded with the change, delivered after commit, retried |
| one instance, one admin call | `Client\TridentClient` (typed; `raw()`/`payload()` for any field) |
| the same call on every configured instance | `Admin\Fleet` — one `InstanceResult` per instance, a dead one reported, never thrown |
| a purge with every engine option | `TridentClient::purge(PurgeRequest …)` |
| an endpoint the client has no method for yet | `Admin\Api::call()` — the one request path the client itself uses |

## 1.6.0 (unreleased)

- **`Admin\WafView`** — the part of an instance's WAF export
  (`wafExport()`) and learned query scopes (`denoiserQueryScopes()`) that
  belongs to one shop: `deadZones($export, $hosts)` and `noise($scopes,
  $hosts)` keep the shop's own host(s), plus the `*` rows marked `inert`
  (no `*` fallback in the engine: old wildcard pins that apply to nothing and
  can be removed with unpin). The export is global
  on a shared Trident; a shop's screen must not list other shops' paths. Shared
  by the WooCommerce plugin and the Shopware plugin (it used to live in the
  WooCommerce plugin only).
- **Denoiser pins need a real host.** `denoiserQueryPin()` and
  `denoiserPathPin()` throw `InvalidRequest` for host `*` or an empty host
  (including the old `*` default). The engine keys learned scopes and zones as
  `{host}|{path_prefix}` with the request's host and has no wildcard fallback,
  so such a pin was accepted and never applied. Unpin and zone/scope delete
  still take `*`, to clean such pins up. **Behaviour change:** a caller that
  pinned on `*` (or relied on the default) now gets an exception instead of a
  silently inert pin.
- **`purgeUrls()` is fixed.** In 1.5 it was never acknowledged and always
  reported 0 purged: the engine's bulk answer carries `total_purged`, not
  `purged`. And absolute URLs purged nothing, because the bulk endpoint takes
  paths plus one `host`/`scheme`. Now:
  - URLs are grouped by origin (paths form a group of their own), one request
    per group;
  - each group is judged on its own (a 200 with the schema or a 202
    `recorded`), and the result is acknowledged only when every group is;
  - every request failing throws, as 1.5 did;
  - some failing gives `isSuccess()` false and `isAcknowledged()` false, with
    the failed groups in `failure` and `raw()['failed_groups']`;
  - an answer that is not an acknowledgement counts as a success, as in 1.5,
    but not as acknowledged;
  - `purgedCount` is the sum of the groups' `total_purged`.
- **`SiteUrl::parse()`** drops the scheme's default port
  (`https://shop.example:443/a` → host `shop.example`) and converts an IDN host
  to punycode, so `purgeUrl()`/`purgeUrls()` hit the browser's key. The IDN
  conversion needs ext-intl; without it a non-ASCII host is kept as written.
- **Denoiser pin hosts** are sent trimmed and lowercased, as the engine keys
  them.
- **API URLs are `scheme://host[:port][/base-path]` only** (`Instances::parse`,
  `Instances::isHttpUrl`). A query, fragment or credentials are refused: the
  client appends `/admin/…`, and a query string would carry that path to
  another service.
- **`ApiError` clips answer bodies** (message and `engineMessage`) to 300
  characters without control characters. What answered is not necessarily
  Trident, and its body must never land on a screen whole.
- **Per-kind overflow tags (`TagSet`, optional):** pass `$overflowFamilies`
  (regex → overflow tag) and a dropped tag of a family adds THAT family's
  overflow tag instead of the general one; `TagSet::overflowTagsFor()` gives a
  purge only the overflow tags of the kinds it carries. An over-tagged listing
  is then refreshed by a product save, not by every unrelated save. Without the
  argument nothing changes.
- **`coverage()` takes the method** the engine checks (`CacheCoverageRequest.method`,
  default GET): `coverage($paths, $host, $scheme, 'HEAD')`.

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
