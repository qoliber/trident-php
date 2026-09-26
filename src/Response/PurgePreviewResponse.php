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

class PurgePreviewResponse
{
    /**
     * @param array<string> $keys
     * @param array<string> $tags
     */
    public function __construct(
        public readonly int $wouldPurge,
        public readonly array $keys,
        public readonly array $tags,
        public readonly int $estimatedBytes
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            wouldPurge: (int) ($data['would_purge'] ?? $data['count'] ?? 0),
            keys: (array) ($data['keys'] ?? []),
            tags: (array) ($data['tags'] ?? []),
            estimatedBytes: (int) ($data['estimated_bytes'] ?? 0)
        );
    }

    public function getWouldPurge(): int
    {
        return $this->wouldPurge;
    }

    /**
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getEstimatedBytes(): int
    {
        return $this->estimatedBytes;
    }

    public function isEmpty(): bool
    {
        return $this->wouldPurge === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'would_purge' => $this->wouldPurge,
            'keys' => $this->keys,
            'tags' => $this->tags,
            'estimated_bytes' => $this->estimatedBytes,
        ];
    }
}
