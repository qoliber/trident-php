<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

/**
 * Simple router for Trident PHP Library Test Application
 */

require_once '/var/www/vendor/autoload.php';

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = $_GET;

// Route the request
$routes = [
    // Health check
    'GET /health' => fn() => health(),

    // Products API (cacheable)
    'GET /api/products' => fn() => getProducts(),
    'GET /api/products/{id}' => fn($id) => getProduct((int)$id),

    // Categories API (cacheable)
    'GET /api/categories' => fn() => getCategories(),
    'GET /api/categories/{id}' => fn($id) => getCategory((int)$id),

    // Pages API (cacheable)
    'GET /api/pages/{slug}' => fn($slug) => getPage($slug),

    // Cart API (not cacheable)
    'GET /api/cart' => fn() => getCart(),
    'POST /api/cart' => fn() => addToCart(),

    // Test endpoints for cache behavior
    'GET /test/tags' => fn() => testTags(),
    'GET /test/ttl/{seconds}' => fn($seconds) => testTtl((int)$seconds),
    'GET /test/vary' => fn() => testVary(),
    'GET /test/nocache' => fn() => testNoCache(),

    // Static pages
    'GET /' => fn() => homepage(),
];

// Match route
$matched = false;
foreach ($routes as $route => $handler) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);

    if ($method !== $routeMethod) {
        continue;
    }

    // Convert route pattern to regex
    $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        $matched = true;
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        try {
            $result = $handler(...array_values($params));
            if (is_array($result)) {
                header('Content-Type: application/json');
                echo json_encode($result, JSON_PRETTY_PRINT);
            }
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'internal_error',
                'message' => $e->getMessage(),
            ]);
        }
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'not_found',
        'message' => 'Route not found',
        'uri' => $uri,
    ]);
}

// ============================================
// Route Handlers
// ============================================

function health(): array
{
    header('Cache-Control: no-store');
    return ['status' => 'ok', 'timestamp' => date('c')];
}

function homepage(): array
{
    header('X-Cache-Tags: page.home,global');
    header('Cache-Control: public, max-age=3600');

    return [
        'page' => 'home',
        'title' => 'Trident PHP Library Test App',
        'cached_at' => date('c'),
    ];
}

function getProducts(): array
{
    header('X-Cache-Tags: product.list,store.default');
    header('Cache-Control: public, max-age=300');

    $products = loadProducts();

    return [
        'data' => $products,
        'count' => count($products),
        'cached_at' => date('c'),
    ];
}

function getProduct(int $id): array
{
    $products = loadProducts();
    $product = $products[$id] ?? null;

    if (!$product) {
        http_response_code(404);
        header('Cache-Control: no-store');
        return ['error' => 'not_found', 'message' => "Product {$id} not found"];
    }

    header("X-Cache-Tags: product.{$id},category.{$product['category_id']},store.default");
    header('Cache-Control: public, max-age=300');

    return [
        'data' => $product,
        'cached_at' => date('c'),
    ];
}

function getCategories(): array
{
    header('X-Cache-Tags: category.list,store.default');
    header('Cache-Control: public, max-age=600');

    $categories = loadCategories();

    return [
        'data' => $categories,
        'count' => count($categories),
        'cached_at' => date('c'),
    ];
}

function getCategory(int $id): array
{
    $categories = loadCategories();
    $category = $categories[$id] ?? null;

    if (!$category) {
        http_response_code(404);
        header('Cache-Control: no-store');
        return ['error' => 'not_found', 'message' => "Category {$id} not found"];
    }

    header("X-Cache-Tags: category.{$id},store.default");
    header('Cache-Control: public, max-age=600');

    return [
        'data' => $category,
        'cached_at' => date('c'),
    ];
}

function getPage(string $slug): array
{
    $pages = [
        'about' => ['id' => 1, 'slug' => 'about', 'title' => 'About Us', 'content' => 'About page content'],
        'contact' => ['id' => 2, 'slug' => 'contact', 'title' => 'Contact', 'content' => 'Contact page content'],
        'faq' => ['id' => 3, 'slug' => 'faq', 'title' => 'FAQ', 'content' => 'FAQ page content'],
    ];

    $page = $pages[$slug] ?? null;

    if (!$page) {
        http_response_code(404);
        header('Cache-Control: no-store');
        return ['error' => 'not_found', 'message' => "Page '{$slug}' not found"];
    }

    header("X-Cache-Tags: page.{$page['id']},cms.page");
    header('Cache-Control: public, max-age=3600');

    return [
        'data' => $page,
        'cached_at' => date('c'),
    ];
}

function getCart(): array
{
    header('Cache-Control: private, no-store');

    return [
        'items' => [],
        'total' => 0,
        'session_id' => session_id() ?: 'no-session',
    ];
}

function addToCart(): array
{
    header('Cache-Control: no-store');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    return [
        'success' => true,
        'message' => 'Item added to cart',
        'product_id' => $input['product_id'] ?? null,
    ];
}

function testTags(): array
{
    // Multiple tags for testing AND/OR purge
    $tags = [
        'test.tag1',
        'test.tag2',
        'test.tag3',
        'featured',
        'category.electronics',
    ];

    header('X-Cache-Tags: ' . implode(',', $tags));
    header('Cache-Control: public, max-age=3600');

    return [
        'test' => 'tags',
        'tags' => $tags,
        'cached_at' => date('c'),
        'random' => bin2hex(random_bytes(8)),
    ];
}

function testTtl(int $seconds): array
{
    header("X-Cache-Tags: test.ttl.{$seconds}");
    header("Cache-Control: public, max-age={$seconds}");

    return [
        'test' => 'ttl',
        'ttl_seconds' => $seconds,
        'cached_at' => date('c'),
        'expires_at' => date('c', time() + $seconds),
        'random' => bin2hex(random_bytes(8)),
    ];
}

function testVary(): array
{
    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    header('X-Cache-Tags: test.vary');
    header('Cache-Control: public, max-age=300');
    header('Vary: Accept-Language, User-Agent');

    return [
        'test' => 'vary',
        'accept_language' => $acceptLang,
        'user_agent' => substr($userAgent, 0, 50),
        'cached_at' => date('c'),
        'random' => bin2hex(random_bytes(8)),
    ];
}

function testNoCache(): array
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    return [
        'test' => 'nocache',
        'should_not_cache' => true,
        'timestamp' => date('c'),
        'random' => bin2hex(random_bytes(8)),
    ];
}

// ============================================
// Data Helpers
// ============================================

function loadProducts(): array
{
    return [
        1 => ['id' => 1, 'name' => 'Laptop Pro', 'price' => 1299.99, 'category_id' => 1, 'featured' => true],
        2 => ['id' => 2, 'name' => 'Wireless Mouse', 'price' => 49.99, 'category_id' => 1, 'featured' => false],
        3 => ['id' => 3, 'name' => 'USB-C Hub', 'price' => 79.99, 'category_id' => 2, 'featured' => true],
        4 => ['id' => 4, 'name' => 'Mechanical Keyboard', 'price' => 149.99, 'category_id' => 1, 'featured' => false],
        5 => ['id' => 5, 'name' => 'Monitor 27"', 'price' => 399.99, 'category_id' => 3, 'featured' => true],
    ];
}

function loadCategories(): array
{
    return [
        1 => ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
        2 => ['id' => 2, 'name' => 'Accessories', 'slug' => 'accessories'],
        3 => ['id' => 3, 'name' => 'Displays', 'slug' => 'displays'],
    ];
}
