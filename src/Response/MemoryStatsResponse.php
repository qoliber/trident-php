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

class MemoryStatsResponse
{
    public function __construct(
        public readonly int $totalBytes,
        public readonly int $cacheBodyBytes,
        public readonly int $cacheMetadataBytes,
        public readonly int $tagIndexBytes,
        public readonly int $bufferBytes,
        public readonly int $compressionSavedBytes,
        public readonly float $usagePercent,
        public readonly int $maxMemory,
        public readonly int $rssBytes = 0,
        public readonly int $trackedBytes = 0
    ) {
    }

    /**
     * Reads the engine's `GET /admin/memory` snapshot (`snapshot.categories`
     * with a `resident`/`transient` band per category, and the allocator's
     * RSS), and the older flat shape.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $snapshot = $data['snapshot'] ?? null;
        if (is_array($snapshot) && isset($snapshot['categories']) && is_array($snapshot['categories'])) {
            $bytes = [];
            $transient = 0;
            foreach ($snapshot['categories'] as $row) {
                if (!is_array($row) || !isset($row['category'])) {
                    continue;
                }
                $logical = (int) ($row['logical_bytes'] ?? 0);
                $bytes[(string) $row['category']] = $logical;
                if (($row['band'] ?? '') === 'transient') {
                    $transient += $logical;
                }
            }
            $rss = (int) ($snapshot['allocator']['rss_bytes'] ?? 0);
            return new self(
                totalBytes: $rss,
                cacheBodyBytes: $bytes['cache_entry_bodies'] ?? 0,
                cacheMetadataBytes: ($bytes['cache_entry_headers'] ?? 0) + ($bytes['cache_entry_keys'] ?? 0)
                    + ($bytes['cache_entry_struct'] ?? 0) + ($bytes['url_index'] ?? 0),
                tagIndexBytes: $bytes['tag_index'] ?? 0,
                bufferBytes: $transient,
                compressionSavedBytes: 0,
                usagePercent: 0.0,
                maxMemory: 0,
                rssBytes: $rss,
                trackedBytes: (int) ($snapshot['tracked_logical_bytes'] ?? 0)
            );
        }
        return new self(
            totalBytes: (int) ($data['total_bytes'] ?? $data['total'] ?? 0),
            cacheBodyBytes: (int) ($data['cache_body_bytes'] ?? $data['bodies'] ?? 0),
            cacheMetadataBytes: (int) ($data['cache_metadata_bytes'] ?? $data['metadata'] ?? 0),
            tagIndexBytes: (int) ($data['tag_index_bytes'] ?? $data['tag_index'] ?? 0),
            bufferBytes: (int) ($data['buffer_bytes'] ?? $data['buffers'] ?? 0),
            compressionSavedBytes: (int) ($data['compression_saved_bytes'] ?? 0),
            usagePercent: (float) ($data['usage_percent'] ?? 0.0),
            maxMemory: (int) ($data['max_memory'] ?? 0)
        );
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getCacheBodyBytes(): int
    {
        return $this->cacheBodyBytes;
    }

    public function getCacheMetadataBytes(): int
    {
        return $this->cacheMetadataBytes;
    }

    public function getTagIndexBytes(): int
    {
        return $this->tagIndexBytes;
    }

    public function getBufferBytes(): int
    {
        return $this->bufferBytes;
    }

    public function getCompressionSavedBytes(): int
    {
        return $this->compressionSavedBytes;
    }

    public function getUsagePercent(): float
    {
        return $this->usagePercent;
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getTotalFormatted(): string
    {
        return $this->formatBytes($this->totalBytes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_bytes' => $this->totalBytes,
            'cache_body_bytes' => $this->cacheBodyBytes,
            'cache_metadata_bytes' => $this->cacheMetadataBytes,
            'tag_index_bytes' => $this->tagIndexBytes,
            'buffer_bytes' => $this->bufferBytes,
            'compression_saved_bytes' => $this->compressionSavedBytes,
            'usage_percent' => $this->usagePercent,
            'max_memory' => $this->maxMemory,
        ];
    }
}
