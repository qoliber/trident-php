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

class CacheEntryResponse
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public readonly bool $found,
        public readonly ?string $storageKey = null,
        public readonly ?string $status = null,
        public readonly ?int $ttlRemaining = null,
        public readonly ?int $age = null,
        public readonly array $tags = [],
        public readonly ?int $hits = null,
        public readonly ?int $statusCode = null,
        public readonly ?int $contentLength = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            found: (bool) ($data['found'] ?? false),
            storageKey: $data['storage_key'] ?? null,
            status: $data['status'] ?? null,
            ttlRemaining: isset($data['ttl_remaining']) ? (int) $data['ttl_remaining'] : null,
            age: isset($data['age']) ? (int) $data['age'] : null,
            tags: $data['tags'] ?? [],
            hits: isset($data['hits']) ? (int) $data['hits'] : null,
            statusCode: isset($data['status_code']) ? (int) $data['status_code'] : null,
            contentLength: isset($data['content_length']) ? (int) $data['content_length'] : null
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

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }
}
