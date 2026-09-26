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

class CacheEntriesResponse
{
    /**
     * @param array<CacheEntryItem> $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly int $total,
        public readonly int $offset,
        public readonly int $limit,
        public readonly bool $hasMore
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entries = array_map(
            fn(array $item) => CacheEntryItem::fromArray($item),
            (array) ($data['entries'] ?? [])
        );

        return new self(
            entries: $entries,
            total: (int) ($data['total'] ?? count($entries)),
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 50),
            hasMore: (bool) ($data['has_more'] ?? false)
        );
    }

    /**
     * @return array<CacheEntryItem>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function isEmpty(): bool
    {
        return empty($this->entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entries' => array_map(fn(CacheEntryItem $e) => $e->toArray(), $this->entries),
            'total' => $this->total,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'has_more' => $this->hasMore,
        ];
    }
}

class CacheEntryItem
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public readonly string $key,
        public readonly string $storageKey,
        public readonly string $status,
        public readonly int $ttlRemaining,
        public readonly int $age,
        public readonly array $tags,
        public readonly int $hits,
        public readonly int $statusCode,
        public readonly int $contentLength,
        public readonly ?string $contentType = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? $data['url_key'] ?? ''),
            storageKey: (string) ($data['storage_key'] ?? $data['hash'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            ttlRemaining: (int) ($data['ttl_remaining'] ?? 0),
            age: (int) ($data['age'] ?? 0),
            tags: (array) ($data['tags'] ?? []),
            hits: (int) ($data['hits'] ?? 0),
            statusCode: (int) ($data['status_code'] ?? 200),
            contentLength: (int) ($data['content_length'] ?? $data['size'] ?? 0),
            contentType: isset($data['content_type']) && is_string($data['content_type']) ? $data['content_type'] : null
        );
    }

    public function isFresh(): bool
    {
        return $this->status === 'fresh';
    }

    public function isStale(): bool
    {
        return $this->status === 'stale';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'storage_key' => $this->storageKey,
            'status' => $this->status,
            'ttl_remaining' => $this->ttlRemaining,
            'age' => $this->age,
            'tags' => $this->tags,
            'hits' => $this->hits,
            'status_code' => $this->statusCode,
            'content_length' => $this->contentLength,
        ];
    }
}
