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
use Qoliber\Trident\Response\CacheEntryResponse;

class CacheEntryResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'found' => true,
            'storage_key' => 'cache:products:1',
            'status' => 'fresh',
            'ttl_remaining' => 300,
            'age' => 60,
            'tags' => ['product.1', 'category.shoes', 'store.default'],
            'hits' => 150,
            'status_code' => 200,
            'content_length' => 5000,
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertTrue($response->found);
        $this->assertEquals('cache:products:1', $response->storageKey);
        $this->assertEquals('fresh', $response->status);
        $this->assertEquals(300, $response->ttlRemaining);
        $this->assertEquals(60, $response->age);
        $this->assertEquals(['product.1', 'category.shoes', 'store.default'], $response->tags);
        $this->assertEquals(150, $response->hits);
        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals(5000, $response->contentLength);
    }

    public function testFromArrayWithNotFound(): void
    {
        $data = [
            'found' => false,
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertFalse($response->found);
        $this->assertNull($response->storageKey);
        $this->assertNull($response->status);
        $this->assertNull($response->ttlRemaining);
        $this->assertNull($response->age);
        $this->assertEmpty($response->tags);
        $this->assertNull($response->hits);
        $this->assertNull($response->statusCode);
        $this->assertNull($response->contentLength);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = CacheEntryResponse::fromArray([]);

        $this->assertFalse($response->found);
        $this->assertEmpty($response->tags);
    }

    public function testIsFreshWithFreshStatus(): void
    {
        $data = [
            'found' => true,
            'status' => 'fresh',
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertTrue($response->isFresh());
        $this->assertFalse($response->isStale());
        $this->assertFalse($response->isExpired());
    }

    public function testIsStaleWithStaleStatus(): void
    {
        $data = [
            'found' => true,
            'status' => 'stale',
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertFalse($response->isFresh());
        $this->assertTrue($response->isStale());
        $this->assertFalse($response->isExpired());
    }

    public function testIsExpiredWithExpiredStatus(): void
    {
        $data = [
            'found' => true,
            'status' => 'expired',
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertFalse($response->isFresh());
        $this->assertFalse($response->isStale());
        $this->assertTrue($response->isExpired());
    }

    public function testStatusMethodsWithNullStatus(): void
    {
        $data = [
            'found' => false,
        ];

        $response = CacheEntryResponse::fromArray($data);

        $this->assertFalse($response->isFresh());
        $this->assertFalse($response->isStale());
        $this->assertFalse($response->isExpired());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $tags = ['tag1', 'tag2'];

        $response = new CacheEntryResponse(
            found: true,
            storageKey: 'key:123',
            status: 'fresh',
            ttlRemaining: 600,
            age: 120,
            tags: $tags,
            hits: 50,
            statusCode: 200,
            contentLength: 1024
        );

        $this->assertTrue($response->found);
        $this->assertEquals('key:123', $response->storageKey);
        $this->assertEquals('fresh', $response->status);
        $this->assertEquals(600, $response->ttlRemaining);
        $this->assertEquals(120, $response->age);
        $this->assertEquals($tags, $response->tags);
        $this->assertEquals(50, $response->hits);
        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals(1024, $response->contentLength);
    }
}
