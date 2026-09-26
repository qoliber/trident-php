<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

/**
 * Merges outbox entries, in order, into purge requests of at most 1000 unique
 * tags. Trident's admin API refuses a body over `max_body_size` (1 MiB by
 * default) with 413, so one huge request would be refused whole; 1000 tags is
 * a few dozen KiB.
 */
final class Packer
{
    public const MAX_TAGS_PER_REQUEST = 1000;

    /**
     * @param list<OutboxEntry> $entries Oldest first.
     * @return list<array{entries: list<OutboxEntry>, tags: list<string>}>
     */
    public static function pack(array $entries, int $max = self::MAX_TAGS_PER_REQUEST): array
    {
        $requests = [];
        $batch = [];
        $tags = [];
        foreach ($entries as $entry) {
            $merged = $tags + array_fill_keys($entry->tags, true);
            if ($batch !== [] && count($merged) > $max) {
                $requests[] = ['entries' => $batch, 'tags' => array_map('strval', array_keys($tags))];
                $batch = [];
                $merged = array_fill_keys($entry->tags, true);
            }
            $batch[] = $entry;
            $tags = $merged;
        }
        if ($batch !== []) {
            $requests[] = ['entries' => $batch, 'tags' => array_map('strval', array_keys($tags))];
        }
        return $requests;
    }

    /**
     * Split tags into chunks one outbox row may hold (deduplicated).
     *
     * @param list<string> $tags
     * @return list<list<string>>
     */
    public static function chunk(array $tags): array
    {
        $unique = array_values(array_unique($tags));
        return $unique === [] ? [] : array_chunk($unique, self::MAX_TAGS_PER_REQUEST);
    }
}
