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
use Qoliber\Trident\Response\PurgePreviewResponse;

class PurgePreviewResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'would_purge' => 5,
            'keys' => ['key1', 'key2', 'key3', 'key4', 'key5'],
            'tags' => ['product.1', 'category.2'],
            'estimated_bytes' => 10240,
        ];

        $response = PurgePreviewResponse::fromArray($data);

        $this->assertEquals(5, $response->wouldPurge);
        $this->assertCount(5, $response->keys);
        $this->assertCount(2, $response->tags);
        $this->assertEquals(10240, $response->estimatedBytes);
    }

    public function testFromArrayAlternativeFieldNames(): void
    {
        $data = [
            'count' => 3,
            'keys' => ['a', 'b', 'c'],
            'tags' => [],
            'estimated_bytes' => 5000,
        ];

        $response = PurgePreviewResponse::fromArray($data);

        $this->assertEquals(3, $response->getWouldPurge());
    }

    public function testIsEmpty(): void
    {
        $emptyResponse = PurgePreviewResponse::fromArray([
            'would_purge' => 0,
            'keys' => [],
            'tags' => [],
            'estimated_bytes' => 0,
        ]);

        $this->assertTrue($emptyResponse->isEmpty());

        $nonEmptyResponse = PurgePreviewResponse::fromArray([
            'would_purge' => 1,
            'keys' => ['key1'],
            'tags' => [],
            'estimated_bytes' => 100,
        ]);

        $this->assertFalse($nonEmptyResponse->isEmpty());
    }

    public function testGetters(): void
    {
        $data = [
            'would_purge' => 2,
            'keys' => ['k1', 'k2'],
            'tags' => ['t1'],
            'estimated_bytes' => 500,
        ];

        $response = PurgePreviewResponse::fromArray($data);

        $this->assertEquals(2, $response->getWouldPurge());
        $this->assertEquals(['k1', 'k2'], $response->getKeys());
        $this->assertEquals(['t1'], $response->getTags());
        $this->assertEquals(500, $response->getEstimatedBytes());
    }

    public function testToArray(): void
    {
        $data = [
            'would_purge' => 2,
            'keys' => ['k1', 'k2'],
            'tags' => ['t1'],
            'estimated_bytes' => 500,
        ];

        $response = PurgePreviewResponse::fromArray($data);
        $output = $response->toArray();

        $this->assertEquals($data, $output);
    }

    public function testDefaultValues(): void
    {
        $response = PurgePreviewResponse::fromArray([]);

        $this->assertEquals(0, $response->wouldPurge);
        $this->assertEquals([], $response->keys);
        $this->assertEquals([], $response->tags);
        $this->assertEquals(0, $response->estimatedBytes);
    }
}
