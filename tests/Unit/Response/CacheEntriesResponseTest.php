<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Response\CacheEntriesResponse;
use Qoliber\Trident\Response\CacheEntryItem;

class CacheEntriesResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'entries' => [
                [
                    'key' => '/products/1',
                    'storage_key' => 'abc123',
                    'status' => 'fresh',
                    'ttl_remaining' => 3600,
                    'age' => 300,
                    'tags' => ['product.1', 'category.5'],
                    'hits' => 10,
                    'status_code' => 200,
                    'content_length' => 5000,
                ],
                [
                    'key' => '/products/2',
                    'storage_key' => 'def456',
                    'status' => 'stale',
                    'ttl_remaining' => 0,
                    'age' => 7200,
                    'tags' => ['product.2'],
                    'hits' => 5,
                    'status_code' => 200,
                    'content_length' => 3000,
                ],
            ],
            'total' => 100,
            'offset' => 0,
            'limit' => 50,
            'has_more' => true,
        ];

        $response = CacheEntriesResponse::fromArray($data);

        $this->assertCount(2, $response->entries);
        $this->assertEquals(100, $response->total);
        $this->assertEquals(0, $response->offset);
        $this->assertEquals(50, $response->limit);
        $this->assertTrue($response->hasMore);
    }

    public function testCacheEntryItem(): void
    {
        $data = [
            'key' => '/products/1',
            'storage_key' => 'abc123',
            'status' => 'fresh',
            'ttl_remaining' => 3600,
            'age' => 300,
            'tags' => ['product.1'],
            'hits' => 10,
            'status_code' => 200,
            'content_length' => 5000,
        ];

        $item = CacheEntryItem::fromArray($data);

        $this->assertEquals('/products/1', $item->key);
        $this->assertEquals('abc123', $item->storageKey);
        $this->assertEquals('fresh', $item->status);
        $this->assertTrue($item->isFresh());
        $this->assertFalse($item->isStale());
    }

    public function testCacheEntryItemStaleStatus(): void
    {
        $item = CacheEntryItem::fromArray([
            'key' => '/test',
            'status' => 'stale',
        ]);

        $this->assertTrue($item->isStale());
        $this->assertFalse($item->isFresh());
    }

    public function testCacheEntryItemAlternativeFieldNames(): void
    {
        $data = [
            'url_key' => '/alt/path',
            'hash' => 'xyz789',
        ];

        $item = CacheEntryItem::fromArray($data);

        $this->assertEquals('/alt/path', $item->key);
        $this->assertEquals('xyz789', $item->storageKey);
    }

    public function testIsEmpty(): void
    {
        $emptyResponse = CacheEntriesResponse::fromArray([
            'entries' => [],
            'total' => 0,
        ]);

        $this->assertTrue($emptyResponse->isEmpty());

        $nonEmptyResponse = CacheEntriesResponse::fromArray([
            'entries' => [['key' => '/test']],
            'total' => 1,
        ]);

        $this->assertFalse($nonEmptyResponse->isEmpty());
    }

    public function testGetters(): void
    {
        $response = CacheEntriesResponse::fromArray([
            'entries' => [['key' => '/test']],
            'total' => 10,
            'offset' => 5,
            'limit' => 50,
            'has_more' => true,
        ]);

        $this->assertCount(1, $response->getEntries());
        $this->assertEquals(10, $response->getTotal());
        $this->assertEquals(5, $response->getOffset());
        $this->assertEquals(50, $response->getLimit());
        $this->assertTrue($response->hasMore());
    }

    public function testToArray(): void
    {
        $response = CacheEntriesResponse::fromArray([
            'entries' => [
                [
                    'key' => '/test',
                    'storage_key' => 'hash1',
                    'status' => 'fresh',
                    'ttl_remaining' => 100,
                    'age' => 50,
                    'tags' => ['t1'],
                    'hits' => 5,
                    'status_code' => 200,
                    'content_length' => 1000,
                ],
            ],
            'total' => 1,
            'offset' => 0,
            'limit' => 50,
            'has_more' => false,
        ]);

        $output = $response->toArray();

        $this->assertArrayHasKey('entries', $output);
        $this->assertArrayHasKey('total', $output);
        $this->assertArrayHasKey('has_more', $output);
    }

    public function testDefaultValues(): void
    {
        $response = CacheEntriesResponse::fromArray([]);

        $this->assertEquals([], $response->entries);
        $this->assertEquals(0, $response->total);
        $this->assertEquals(0, $response->offset);
        $this->assertEquals(50, $response->limit);
        $this->assertFalse($response->hasMore);
    }
}
