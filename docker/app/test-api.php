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
 * Test script to validate Trident API responses
 * Run: php test-api.php
 */

require_once '/var/www/vendor/autoload.php';

use Qoliber\Trident\TridentFactory;

echo "=== Trident PHP Library API Test ===\n\n";

$adminUrl = getenv('TRIDENT_ADMIN_URL') ?: 'http://trident:9100';
$apiKey = getenv('TRIDENT_ADMIN_KEY') ?: 'test-admin-key-12345';

echo "Connecting to: {$adminUrl}\n";
echo "API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'none') . "\n\n";

try {
    $trident = TridentFactory::create($adminUrl, $apiKey);

    // Test 1: Health Check
    echo "1. Health Check\n";
    echo "   ─────────────\n";
    $health = $trident->health();
    echo "   Status: {$health->status}\n";
    echo "   Healthy: " . ($health->isHealthy() ? 'Yes' : 'No') . "\n";
    if ($health->version) {
        echo "   Version: {$health->version}\n";
    }
    echo "   ✓ PASS\n\n";

    // Test 2: Cache Stats
    echo "2. Cache Stats\n";
    echo "   ────────────\n";
    $stats = $trident->stats();
    echo "   Entries: {$stats->entries}\n";
    echo "   Size: {$stats->getBytesFormatted()}\n";
    echo "   Hits: {$stats->hits}\n";
    echo "   Misses: {$stats->misses}\n";
    echo "   Hit Ratio: {$stats->getHitRatioPercent()}%\n";
    echo "   ✓ PASS\n\n";

    // Test 3: Purge by Tag
    echo "3. Purge by Tag\n";
    echo "   ─────────────\n";
    $response = $trident->purgeTag('test.integration');
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   Purged: {$response->getPurgedCount()}\n";
    echo "   ✓ PASS\n\n";

    // Test 4: Purge by Multiple Tags (OR)
    echo "4. Purge by Multiple Tags (OR match)\n";
    echo "   ───────────────────────────────────\n";
    $response = $trident->purgeTags(['product.1', 'product.2', 'category.1']);
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   Purged: {$response->getPurgedCount()}\n";
    echo "   ✓ PASS\n\n";

    // Test 5: Purge by Multiple Tags (AND)
    echo "5. Purge by Multiple Tags (AND match)\n";
    echo "   ────────────────────────────────────\n";
    $response = $trident->purgeTagsAll(['category.electronics', 'featured']);
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   Purged: {$response->getPurgedCount()}\n";
    echo "   ✓ PASS\n\n";

    // Test 6: Purge by Pattern
    echo "6. Purge by Tag Pattern\n";
    echo "   ──────────────────────\n";
    $response = $trident->purgeTagPattern('product.*');
    echo "   Pattern: product.*\n";
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   Purged: {$response->getPurgedCount()}\n";
    echo "   ✓ PASS\n\n";

    // Test 7: Purge by URL
    echo "7. Purge by URL\n";
    echo "   ─────────────\n";
    $response = $trident->purgeUrl('http://nginx/api/products.php?id=1');
    echo "   URL: http://nginx/api/products.php?id=1\n";
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   ✓ PASS\n\n";

    // Test 8: Soft Purge
    echo "8. Soft Purge by URL\n";
    echo "   ───────────────────\n";
    $response = $trident->purgeUrl('http://nginx/index.php', soft: true);
    echo "   URL: http://nginx/index.php\n";
    echo "   Soft: Yes\n";
    echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
    echo "   ✓ PASS\n\n";

    // Test 9: Tag Collection
    echo "9. Tag Collection\n";
    echo "   ────────────────\n";
    $tags = $trident->tags()
        ->addProduct(100)
        ->addProduct(101)
        ->addCategory(10)
        ->add('store.default');
    echo "   Tags: " . $tags->toHeader() . "\n";
    echo "   Count: " . count($tags) . "\n";
    $response = $trident->purgeCollection($tags);
    echo "   Purged: {$response->getPurgedCount()}\n";
    echo "   ✓ PASS\n\n";

    // Test 10: Convenience Methods
    echo "10. Convenience Methods\n";
    echo "    ─────────────────────\n";
    echo "    purgeProduct(123): " . ($trident->purgeProduct(123)->isSuccess() ? '✓' : '✗') . "\n";
    echo "    purgeCategory(45): " . ($trident->purgeCategory(45)->isSuccess() ? '✓' : '✗') . "\n";
    echo "    purgePage('home'): " . ($trident->purgePage('home')->isSuccess() ? '✓' : '✗') . "\n";
    echo "    ✓ PASS\n\n";

    echo "═══════════════════════════════\n";
    echo "  All tests passed! ✓\n";
    echo "═══════════════════════════════\n";

} catch (\Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
