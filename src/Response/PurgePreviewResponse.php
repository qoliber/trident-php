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
    use CarriesRaw;

    /**
     * @param array<string> $keys
     * @param array<string> $tags
     */
    public function __construct(
        public readonly int $wouldPurge,
        public readonly array $keys,
        public readonly array $tags,
        public readonly int $estimatedBytes,
        public readonly bool $sampleTruncated = false
    ) {
    }

    /**
     * Reads the engine's shape (`would_purge`, `would_free_bytes`, and a
     * `sample` of entries whose `url_key`s become {@see self::$keys}), and the
     * older `keys` / `estimated_bytes`.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = (array) ($data['keys'] ?? []);
        if ($keys === [] && isset($data['sample']) && is_array($data['sample'])) {
            foreach ($data['sample'] as $entry) {
                if (is_array($entry) && isset($entry['url_key']) && is_string($entry['url_key'])) {
                    $keys[] = $entry['url_key'];
                }
            }
        }
        return (new self(
            wouldPurge: (int) ($data['would_purge'] ?? $data['count'] ?? 0),
            keys: $keys,
            tags: (array) ($data['tags'] ?? []),
            estimatedBytes: (int) ($data['would_free_bytes'] ?? $data['estimated_bytes'] ?? 0),
            sampleTruncated: (bool) ($data['sample_truncated'] ?? false)
        ))->attachRaw($data);
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
