<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

header('Content-Type: application/json');

$productId = $_GET['id'] ?? '1';

// Set cache tags based on product
header("X-Cache-Tags: product.{$productId},category.electronics,store.default");
header('Cache-Control: public, max-age=300');

$products = [
    '1' => ['id' => 1, 'name' => 'Laptop Pro', 'price' => 1299.99, 'category' => 'electronics'],
    '2' => ['id' => 2, 'name' => 'Wireless Mouse', 'price' => 49.99, 'category' => 'electronics'],
    '3' => ['id' => 3, 'name' => 'USB-C Hub', 'price' => 79.99, 'category' => 'accessories'],
];

$product = $products[$productId] ?? null;

if ($product) {
    echo json_encode([
        'status' => 'success',
        'data' => $product,
        'cached_at' => date('c'),
    ], JSON_PRETTY_PRINT);
} else {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Product not found',
    ], JSON_PRETTY_PRINT);
}
