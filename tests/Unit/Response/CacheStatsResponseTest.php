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
use Qoliber\Trident\Response\CacheStatsResponse;

class CacheStatsResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'entries' => 100,
            'memory_used' => 1048576,
            'max_memory' => 2097152,
            'evictions' => 5,
            'evicted_bytes' => 50000,
            'memory_usage_percent' => 50.0,
            'compressed_entries' => 80,
            'compression_bytes_original' => 1000000,
            'compression_bytes_compressed' => 500000,
            'compression_ratio' => 2.0,
            'compression_bytes_saved' => 500000,
            'tag_index_memory_bytes' => 10000,
            'unique_tags' => 50,
            'indexed_keys' => 100,
        ];

        $response = CacheStatsResponse::fromArray($data);

        $this->assertEquals(100, $response->entries);
        $this->assertEquals(1048576, $response->memoryUsed);
        $this->assertEquals(2097152, $response->maxMemory);
        $this->assertEquals(5, $response->evictions);
        $this->assertEquals(50000, $response->evictedBytes);
        $this->assertEquals(50.0, $response->memoryUsagePercent);
        $this->assertEquals(80, $response->compressedEntries);
    }

    public function testFromArrayAlternativeFieldNames(): void
    {
        $data = [
            'entry_count' => 50,
            'bytes' => 2048,
        ];

        $response = CacheStatsResponse::fromArray($data);

        $this->assertEquals(50, $response->entries);
        $this->assertEquals(2048, $response->memoryUsed);
    }

    public function testFromArraySizeBytesAlternative(): void
    {
        $data = [
            'entries' => 25,
            'size_bytes' => 4096,
        ];

        $response = CacheStatsResponse::fromArray($data);

        $this->assertEquals(4096, $response->memoryUsed);
    }

    public function testGetters(): void
    {
        $data = [
            'entries' => 100,
            'memory_used' => 1000000,
            'max_memory' => 2000000,
            'evictions' => 10,
            'evicted_bytes' => 50000,
            'memory_usage_percent' => 50.0,
            'compressed_entries' => 75,
            'compression_bytes_original' => 500000,
            'compression_bytes_compressed' => 250000,
            'compression_ratio' => 2.0,
            'compression_bytes_saved' => 250000,
            'tag_index_memory_bytes' => 10000,
            'unique_tags' => 30,
            'indexed_keys' => 100,
        ];

        $response = CacheStatsResponse::fromArray($data);

        $this->assertEquals(100, $response->getEntries());
        $this->assertEquals(1000000, $response->getMemoryUsed());
        $this->assertEquals(2000000, $response->getMaxMemory());
        $this->assertEquals(10, $response->getEvictions());
        $this->assertEquals(50000, $response->getEvictedBytes());
        $this->assertEquals(50.0, $response->getMemoryUsagePercent());
        $this->assertEquals(75, $response->getCompressedEntries());
        $this->assertEquals(500000, $response->getCompressionBytesOriginal());
        $this->assertEquals(250000, $response->getCompressionBytesCompressed());
        $this->assertEquals(2.0, $response->getCompressionRatio());
        $this->assertEquals(250000, $response->getCompressionBytesSaved());
        $this->assertEquals(10000, $response->getTagIndexMemoryBytes());
        $this->assertEquals(30, $response->getUniqueTags());
        $this->assertEquals(100, $response->getIndexedKeys());
    }

    public function testBytesFormatted(): void
    {
        $testCases = [
            [500, '500 B'],
            [1024, '1 KB'],
            [1536, '1.5 KB'],
            [1048576, '1 MB'],
            [1073741824, '1 GB'],
        ];

        foreach ($testCases as [$bytes, $expected]) {
            $response = CacheStatsResponse::fromArray([
                'memory_used' => $bytes,
            ]);

            $this->assertEquals($expected, $response->getBytesFormatted());
        }
    }

    public function testGetHitRatioPercent(): void
    {
        $response = CacheStatsResponse::fromArray([
            'entries' => 100,
        ]);

        // Currently returns 0.0 as placeholder
        $this->assertEquals(0.0, $response->getHitRatioPercent());
    }

    public function testToArray(): void
    {
        $data = [
            'entries' => 100,
            'memory_used' => 1000000,
            'max_memory' => 2000000,
            'evictions' => 5,
            'evicted_bytes' => 25000,
            'memory_usage_percent' => 50.0,
            'compressed_entries' => 50,
            'compression_bytes_original' => 200000,
            'compression_bytes_compressed' => 100000,
            'compression_ratio' => 2.0,
            'compression_bytes_saved' => 100000,
            'tag_index_memory_bytes' => 5000,
            'unique_tags' => 20,
            'indexed_keys' => 100,
        ];

        $response = CacheStatsResponse::fromArray($data);
        $output = $response->toArray();

        $this->assertEquals(100, $output['entries']);
        $this->assertEquals(1000000, $output['memory_used']);
        $this->assertEquals(2000000, $output['max_memory']);
        $this->assertEquals(5, $output['evictions']);
    }

    public function testDefaultValues(): void
    {
        $response = CacheStatsResponse::fromArray([]);

        $this->assertEquals(0, $response->entries);
        $this->assertEquals(0, $response->memoryUsed);
        $this->assertEquals(0, $response->maxMemory);
        $this->assertEquals(0, $response->evictions);
        $this->assertEquals(0, $response->evictedBytes);
        $this->assertEquals(0.0, $response->memoryUsagePercent);
        $this->assertEquals(0, $response->compressedEntries);
        $this->assertEquals(1.0, $response->compressionRatio);
    }
}
