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

class TagStatsResponse
{
    /**
     * @param array<array{tag: string, entries: int}> $tags
     */
    public function __construct(
        public readonly int $totalTags,
        public readonly array $tags
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalTags: (int) ($data['total_tags'] ?? 0),
            tags: $data['tags'] ?? []
        );
    }

    public function getTagEntryCount(string $tag): int
    {
        foreach ($this->tags as $t) {
            if ($t['tag'] === $tag) {
                return (int) $t['entries'];
            }
        }
        return 0;
    }
}
