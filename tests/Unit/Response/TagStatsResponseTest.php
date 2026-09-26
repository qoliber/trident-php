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
use Qoliber\Trident\Response\TagStatsResponse;

class TagStatsResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'total_tags' => 150,
            'tags' => [
                ['tag' => 'product.1', 'entries' => 10],
                ['tag' => 'category.shoes', 'entries' => 25],
                ['tag' => 'store.default', 'entries' => 100],
            ],
        ];

        $response = TagStatsResponse::fromArray($data);

        $this->assertEquals(150, $response->totalTags);
        $this->assertCount(3, $response->tags);
        $this->assertEquals('product.1', $response->tags[0]['tag']);
        $this->assertEquals(10, $response->tags[0]['entries']);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = TagStatsResponse::fromArray([]);

        $this->assertEquals(0, $response->totalTags);
        $this->assertEmpty($response->tags);
    }

    public function testGetTagEntryCountExistingTag(): void
    {
        $data = [
            'total_tags' => 3,
            'tags' => [
                ['tag' => 'product.1', 'entries' => 10],
                ['tag' => 'category.shoes', 'entries' => 25],
                ['tag' => 'store.default', 'entries' => 100],
            ],
        ];

        $response = TagStatsResponse::fromArray($data);

        $this->assertEquals(10, $response->getTagEntryCount('product.1'));
        $this->assertEquals(25, $response->getTagEntryCount('category.shoes'));
        $this->assertEquals(100, $response->getTagEntryCount('store.default'));
    }

    public function testGetTagEntryCountNonExistingTag(): void
    {
        $data = [
            'total_tags' => 1,
            'tags' => [
                ['tag' => 'product.1', 'entries' => 10],
            ],
        ];

        $response = TagStatsResponse::fromArray($data);

        $this->assertEquals(0, $response->getTagEntryCount('nonexistent.tag'));
    }

    public function testGetTagEntryCountWithEmptyTags(): void
    {
        $response = TagStatsResponse::fromArray([]);

        $this->assertEquals(0, $response->getTagEntryCount('any.tag'));
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $tags = [
            ['tag' => 'test.tag', 'entries' => 50],
        ];

        $response = new TagStatsResponse(
            totalTags: 1,
            tags: $tags
        );

        $this->assertEquals(1, $response->totalTags);
        $this->assertEquals($tags, $response->tags);
    }
}
