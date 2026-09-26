<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tags;

/**
 * The tags one cached response carries: bounded, prioritised, normalised.
 *
 * Trident keeps at most `[cache.tags] max_tags_per_entry` tags per entry (200
 * by default) and ignores the rest — silently, from the shop's side: the page
 * stays cached and a purge of a dropped tag misses it. So the set is bounded
 * HERE, where the platform still knows which tags matter:
 *
 *  - IDENTITY — what the page is (site tag, the entity, the listing). Kept.
 *  - REFERENCE — what it mentions (terms in breadcrumbs, a menu rendered inline).
 *  - LISTED — entities rendered in a list. Dropped first; when anything is
 *    dropped the set adds the OVERFLOW tag, which the platform must include in
 *    every purge of a listed entity — so a truncated page is purged by any such
 *    change instead of by none.
 *
 * The header value is also kept under a byte budget: nginx and most proxies in
 * front of PHP refuse response headers over a few KiB with a 502.
 */
final class TagSet
{
    public const IDENTITY = 0;
    public const REFERENCE = 1;
    public const LISTED = 2;

    /** Trident's default `max_tags_per_entry`. */
    public const DEFAULT_MAX_TAGS = 200;

    /** Header value budget in bytes. */
    public const DEFAULT_MAX_BYTES = 4096;

    /** Longest single tag (Trident's `max_tag_length` default is 256). */
    public const MAX_TAG_LENGTH = 100;

    /** @var array<int, array<string, true>> */
    private array $tags = [self::IDENTITY => [], self::REFERENCE => [], self::LISTED => []];

    /** @var array<string, ?string> normalised tag => its family's overflow tag */
    private array $familyOfTag = [];

    /**
     * @param string $prefix   Prepended to every tag (several sites on one Trident).
     * @param string $overflow Unprefixed name of the overflow tag.
     */
    /**
     * @param array<string, string> $overflowFamilies regex (on the tag WITHOUT the prefix) => overflow tag.
     *        A dropped tag matching a family adds that family's overflow tag
     *        instead of the general one, so a purge adds an overflow tag only
     *        for the kinds of tags it carries ({@see overflowTagsFor()}) — an
     *        over-tagged listing is not refreshed by every unrelated purge.
     */
    public function __construct(
        private readonly string $prefix = '',
        private readonly string $overflow = 'list_overflow',
        private readonly int $maxTags = self::DEFAULT_MAX_TAGS,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
        private readonly array $overflowFamilies = []
    ) {
    }

    /**
     * The overflow tags a purge of $tags must also carry: the family tag of
     * every family a tag belongs to, the general one for any other tag.
     *
     * @param iterable<string> $tags un-prefixed tags
     * @param array<string, string> $overflowFamilies as in the constructor
     * @return list<string> normalised, prefixed
     */
    public static function overflowTagsFor(iterable $tags, string $prefix, string $overflow, array $overflowFamilies = []): array
    {
        $out = [];
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if ($tag === '') {
                continue;
            }
            $family = self::familyOf($tag, $overflowFamilies);
            $out[self::normalise($prefix, $family ?? $overflow)] = true;
        }
        return array_map('strval', array_keys($out));
    }

    /**
     * @param array<string, string> $families
     */
    private static function familyOf(string $tag, array $families): ?string
    {
        foreach ($families as $pattern => $overflowTag) {
            if (preg_match($pattern, $tag) === 1) {
                return $overflowTag;
            }
        }
        return null;
    }

    /**
     * Lower case; anything but `a-z 0-9 _ - : .` becomes `_`, so the separator
     * (`,`) and whitespace can never split a tag.
     *
     * @return string Empty when nothing usable is left.
     */
    public static function normalise(string $prefix, string $tag): string
    {
        $tag = strtolower(trim($prefix . $tag));
        $tag = (string) preg_replace('/[^a-z0-9_\-:.]/', '_', $tag);
        $tag = substr($tag, 0, self::MAX_TAG_LENGTH);
        return trim($tag, '_') === '' ? '' : $tag;
    }

    public function add(string $tag, int $priority = self::IDENTITY): void
    {
        $raw = $tag;
        $tag = self::normalise($this->prefix, $tag);
        if ($tag === '') {
            return;
        }
        if ($this->overflowFamilies !== []) {
            $this->familyOfTag[$tag] = self::familyOf($raw, $this->overflowFamilies);
        }
        $priority = max(self::IDENTITY, min(self::LISTED, $priority));
        // A tag keeps its most important role.
        foreach ($this->tags as $p => $set) {
            if (isset($set[$tag])) {
                if ($p <= $priority) {
                    return;
                }
                unset($this->tags[$p][$tag]);
            }
        }
        $this->tags[$priority][$tag] = true;
    }

    /**
     * @param iterable<string> $tags
     */
    public function addAll(iterable $tags, int $priority = self::IDENTITY): void
    {
        foreach ($tags as $tag) {
            $this->add((string) $tag, $priority);
        }
    }

    public function isEmpty(): bool
    {
        return $this->tags[self::IDENTITY] === [] && $this->tags[self::REFERENCE] === [] && $this->tags[self::LISTED] === [];
    }

    /**
     * The tags that fit, most important first.
     *
     * @return list<string>
     */
    public function toArray(): array
    {
        $general = self::normalise($this->prefix, $this->overflow);
        $familyTags = [];
        foreach (array_unique(array_values($this->overflowFamilies)) as $f) {
            $familyTags[self::normalise($this->prefix, $f)] = true;
        }
        // Slots and bytes stay reserved for every overflow tag that may be needed.
        $reserved = array_merge([$general], array_map('strval', array_keys($familyTags)));
        $out = [];
        $bytes = 0;
        $needed = [];
        $slots = max(1, $this->maxTags - count($reserved));
        $budget = $this->maxBytes - array_sum(array_map(static fn (string $t): int => strlen($t) + 1, $reserved));
        foreach ($this->tags as $set) {
            foreach (array_keys($set) as $tag) {
                $tag = (string) $tag;
                if (in_array($tag, $reserved, true)) {
                    $needed[$tag] = true;
                    continue;
                }
                $cost = strlen($tag) + ($out === [] ? 0 : 1);
                if (count($out) >= $slots || $bytes + $cost > $budget) {
                    $family = $this->familyOfTag[$tag] ?? null;
                    $needed[$family !== null ? self::normalise($this->prefix, $family) : $general] = true;
                    continue;
                }
                $out[] = $tag;
                $bytes += $cost;
            }
        }
        foreach ($reserved as $tag) {
            if (isset($needed[$tag])) {
                $out[] = $tag;
            }
        }
        return $out;
    }

    public function headerValue(string $separator = ','): string
    {
        return implode($separator, $this->toArray());
    }
}
