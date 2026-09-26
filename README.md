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
