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
use Qoliber\Trident\Response\CacheTagsResponse;
use Qoliber\Trident\Response\TagItem;

class CacheTagsResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'tags' => [
                ['tag' => 'product.1', 'entries' => 10],
                ['tag' => 'category.2', 'entries' => 25],
            ],
            'total' => 50,
            'offset' => 0,
            'limit' => 100,
            'has_more' => false,
        ];

        $response = CacheTagsResponse::fromArray($data);

        $this->assertCount(2, $response->tags);
        $this->assertEquals(50, $response->total);
        $this->assertEquals(0, $response->offset);
        $this->assertEquals(100, $response->limit);
        $this->assertFalse($response->hasMore);
    }

    public function testTagItem(): void
    {
        $item = TagItem::fromArray([
            'tag' => 'product.123',
            'entries' => 42,
        ]);

        $this->assertEquals('product.123', $item->tag);
        $this->assertEquals(42, $item->entries);
    }

    public function testTagItemAlternativeFieldNames(): void
    {
        $item = TagItem::fromArray([
            'name' => 'category.5',
            'count' => 15,
        ]);

        $this->assertEquals('category.5', $item->tag);
        $this->assertEquals(15, $item->entries);
    }

    public function testIsEmpty(): void
    {
        $emptyResponse = CacheTagsResponse::fromArray([
            'tags' => [],
            'total' => 0,
        ]);

        $this->assertTrue($emptyResponse->isEmpty());

        $nonEmptyResponse = CacheTagsResponse::fromArray([
            'tags' => [['tag' => 'test', 'entries' => 1]],
            'total' => 1,
        ]);

        $this->assertFalse($nonEmptyResponse->isEmpty());
    }

    public function testGetters(): void
    {
        $response = CacheTagsResponse::fromArray([
            'tags' => [['tag' => 'test', 'entries' => 1]],
            'total' => 100,
            'offset' => 10,
            'limit' => 50,
            'has_more' => true,
        ]);

        $this->assertCount(1, $response->getTags());
        $this->assertEquals(100, $response->getTotal());
        $this->assertTrue($response->hasMore());
    }

    public function testToArray(): void
    {
        $response = CacheTagsResponse::fromArray([
            'tags' => [
                ['tag' => 'product.1', 'entries' => 5],
            ],
            'total' => 1,
            'offset' => 0,
            'limit' => 50,
            'has_more' => false,
        ]);

        $output = $response->toArray();

        $this->assertArrayHasKey('tags', $output);
        $this->assertCount(1, $output['tags']);
        $this->assertEquals('product.1', $output['tags'][0]['tag']);
    }

    public function testTagItemToArray(): void
    {
        $item = TagItem::fromArray([
            'tag' => 'test.tag',
            'entries' => 10,
        ]);

        $output = $item->toArray();

        $this->assertEquals('test.tag', $output['tag']);
        $this->assertEquals(10, $output['entries']);
    }

    public function testDefaultValues(): void
    {
        $response = CacheTagsResponse::fromArray([]);

        $this->assertEquals([], $response->tags);
        $this->assertEquals(0, $response->total);
        $this->assertEquals(0, $response->offset);
        $this->assertEquals(50, $response->limit);
        $this->assertFalse($response->hasMore);
    }
}
