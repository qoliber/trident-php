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
use Qoliber\Trident\Response\MemoryStatsResponse;

class MemoryStatsResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'total_bytes' => 1073741824, // 1 GB
            'cache_body_bytes' => 805306368, // 768 MB
            'cache_metadata_bytes' => 134217728, // 128 MB
            'tag_index_bytes' => 67108864, // 64 MB
            'buffer_bytes' => 67108864, // 64 MB
            'compression_saved_bytes' => 268435456, // 256 MB
            'usage_percent' => 75.5,
            'max_memory' => 2147483648, // 2 GB
        ];

        $response = MemoryStatsResponse::fromArray($data);

        $this->assertEquals(1073741824, $response->totalBytes);
        $this->assertEquals(805306368, $response->cacheBodyBytes);
        $this->assertEquals(134217728, $response->cacheMetadataBytes);
        $this->assertEquals(67108864, $response->tagIndexBytes);
        $this->assertEquals(67108864, $response->bufferBytes);
        $this->assertEquals(268435456, $response->compressionSavedBytes);
        $this->assertEquals(75.5, $response->usagePercent);
        $this->assertEquals(2147483648, $response->maxMemory);
    }

    public function testFromArrayAlternativeFieldNames(): void
    {
        $data = [
            'total' => 1000000,
            'bodies' => 600000,
            'metadata' => 200000,
            'tag_index' => 100000,
            'buffers' => 100000,
        ];

        $response = MemoryStatsResponse::fromArray($data);

        $this->assertEquals(1000000, $response->getTotalBytes());
        $this->assertEquals(600000, $response->getCacheBodyBytes());
        $this->assertEquals(200000, $response->getCacheMetadataBytes());
        $this->assertEquals(100000, $response->getTagIndexBytes());
        $this->assertEquals(100000, $response->getBufferBytes());
    }

    public function testGetters(): void
    {
        $response = MemoryStatsResponse::fromArray([
            'total_bytes' => 1000,
            'cache_body_bytes' => 500,
            'cache_metadata_bytes' => 200,
            'tag_index_bytes' => 150,
            'buffer_bytes' => 150,
            'compression_saved_bytes' => 100,
            'usage_percent' => 50.0,
            'max_memory' => 2000,
        ]);

        $this->assertEquals(1000, $response->getTotalBytes());
        $this->assertEquals(500, $response->getCacheBodyBytes());
        $this->assertEquals(200, $response->getCacheMetadataBytes());
        $this->assertEquals(150, $response->getTagIndexBytes());
        $this->assertEquals(150, $response->getBufferBytes());
        $this->assertEquals(100, $response->getCompressionSavedBytes());
        $this->assertEquals(50.0, $response->getUsagePercent());
    }

    public function testFormatBytes(): void
    {
        $response = MemoryStatsResponse::fromArray([
            'total_bytes' => 1073741824, // 1 GB
        ]);

        $testCases = [
            [500, '500 B'],
            [1024, '1 KB'],
            [1536, '1.5 KB'],
            [1048576, '1 MB'],
            [1073741824, '1 GB'],
        ];

        foreach ($testCases as [$bytes, $expected]) {
            $this->assertEquals($expected, $response->formatBytes($bytes));
        }
    }

    public function testGetTotalFormatted(): void
    {
        $response = MemoryStatsResponse::fromArray([
            'total_bytes' => 1073741824,
        ]);

        $this->assertEquals('1 GB', $response->getTotalFormatted());
    }

    public function testToArray(): void
    {
        $data = [
            'total_bytes' => 1000000,
            'cache_body_bytes' => 600000,
            'cache_metadata_bytes' => 200000,
            'tag_index_bytes' => 100000,
            'buffer_bytes' => 100000,
            'compression_saved_bytes' => 50000,
            'usage_percent' => 50.0,
            'max_memory' => 2000000,
        ];

        $response = MemoryStatsResponse::fromArray($data);
        $output = $response->toArray();

        $this->assertEquals($data, $output);
    }

    public function testDefaultValues(): void
    {
        $response = MemoryStatsResponse::fromArray([]);

        $this->assertEquals(0, $response->totalBytes);
        $this->assertEquals(0, $response->cacheBodyBytes);
        $this->assertEquals(0, $response->cacheMetadataBytes);
        $this->assertEquals(0, $response->tagIndexBytes);
        $this->assertEquals(0, $response->bufferBytes);
        $this->assertEquals(0, $response->compressionSavedBytes);
        $this->assertEquals(0.0, $response->usagePercent);
        $this->assertEquals(0, $response->maxMemory);
    }
}
