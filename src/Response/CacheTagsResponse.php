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

class CacheTagsResponse
{
    use CarriesRaw;

    /**
     * @param array<TagItem> $tags
     */
    public function __construct(
        public readonly array $tags,
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
        $tags = array_map(
            fn(array $item) => TagItem::fromArray($item),
            (array) ($data['tags'] ?? [])
        );

        return (new self(
            tags: $tags,
            total: (int) ($data['total'] ?? count($tags)),
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 50),
            hasMore: (bool) ($data['has_more'] ?? false)
        ))->attachRaw($data);
    }

    /**
     * @return array<TagItem>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function isEmpty(): bool
    {
        return empty($this->tags);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tags' => array_map(fn(TagItem $t) => $t->toArray(), $this->tags),
            'total' => $this->total,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'has_more' => $this->hasMore,
        ];
    }
}

class TagItem
{
    use CarriesRaw;

    public function __construct(
        public readonly string $tag,
        public readonly int $entries
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            tag: (string) ($data['tag'] ?? $data['name'] ?? ''),
            entries: (int) ($data['entries'] ?? $data['count'] ?? 0)
        ))->attachRaw($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'entries' => $this->entries,
        ];
    }
}
