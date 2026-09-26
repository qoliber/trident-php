<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Response;

class CacheStatsResponse
{
    public function __construct(
        public readonly int $entries,
        public readonly int $memoryUsed,
        public readonly int $maxMemory,
        public readonly int $evictions,
        public readonly int $evictedBytes,
        public readonly float $memoryUsagePercent,
        public readonly int $compressedEntries,
        public readonly int $compressionBytesOriginal,
        public readonly int $compressionBytesCompressed,
        public readonly float $compressionRatio,
        public readonly int $compressionBytesSaved,
        public readonly int $tagIndexMemoryBytes,
        public readonly int $uniqueTags,
        public readonly int $indexedKeys
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entries: (int) ($data['entries'] ?? $data['entry_count'] ?? 0),
            memoryUsed: (int) ($data['memory_used'] ?? $data['bytes'] ?? $data['size_bytes'] ?? 0),
            maxMemory: (int) ($data['max_memory'] ?? 0),
            evictions: (int) ($data['evictions'] ?? 0),
            evictedBytes: (int) ($data['evicted_bytes'] ?? 0),
            memoryUsagePercent: (float) ($data['memory_usage_percent'] ?? 0.0),
            compressedEntries: (int) ($data['compressed_entries'] ?? 0),
            compressionBytesOriginal: (int) ($data['compression_bytes_original'] ?? 0),
            compressionBytesCompressed: (int) ($data['compression_bytes_compressed'] ?? 0),
            compressionRatio: (float) ($data['compression_ratio'] ?? 1.0),
            compressionBytesSaved: (int) ($data['compression_bytes_saved'] ?? 0),
            tagIndexMemoryBytes: (int) ($data['tag_index_memory_bytes'] ?? 0),
            uniqueTags: (int) ($data['unique_tags'] ?? 0),
            indexedKeys: (int) ($data['indexed_keys'] ?? 0)
        );
    }

    public function getEntries(): int
    {
        return $this->entries;
    }

    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    public function getMaxMemory(): int
    {
        return $this->maxMemory;
    }

    public function getEvictions(): int
    {
        return $this->evictions;
    }

    public function getEvictedBytes(): int
    {
        return $this->evictedBytes;
    }

    public function getMemoryUsagePercent(): float
    {
        return $this->memoryUsagePercent;
    }

    public function getCompressedEntries(): int
    {
        return $this->compressedEntries;
    }

    public function getCompressionBytesOriginal(): int
    {
        return $this->compressionBytesOriginal;
    }

    public function getCompressionBytesCompressed(): int
    {
        return $this->compressionBytesCompressed;
    }

    public function getCompressionRatio(): float
    {
        return $this->compressionRatio;
    }

    public function getCompressionBytesSaved(): int
    {
        return $this->compressionBytesSaved;
    }

    public function getTagIndexMemoryBytes(): int
    {
        return $this->tagIndexMemoryBytes;
    }

    public function getUniqueTags(): int
    {
        return $this->uniqueTags;
    }

    public function getIndexedKeys(): int
    {
        return $this->indexedKeys;
    }

    public function getHitRatioPercent(): float
    {
        // Calculated from hits/misses if available - for now return 0
        return 0.0;
    }

    public function getBytesFormatted(): string
    {
        $bytes = $this->memoryUsed;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entries' => $this->entries,
            'memory_used' => $this->memoryUsed,
            'max_memory' => $this->maxMemory,
            'evictions' => $this->evictions,
            'evicted_bytes' => $this->evictedBytes,
            'memory_usage_percent' => $this->memoryUsagePercent,
            'compressed_entries' => $this->compressedEntries,
            'compression_bytes_original' => $this->compressionBytesOriginal,
            'compression_bytes_compressed' => $this->compressionBytesCompressed,
            'compression_ratio' => $this->compressionRatio,
            'compression_bytes_saved' => $this->compressionBytesSaved,
            'tag_index_memory_bytes' => $this->tagIndexMemoryBytes,
            'unique_tags' => $this->uniqueTags,
            'indexed_keys' => $this->indexedKeys,
        ];
    }
}
