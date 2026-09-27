<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tags;

use Qoliber\Trident\Tags\TagSet;

/**
 * Tag names on both sides — the response header and the purge — so they always
 * match: the prefix (several shops on one Trident), normalisation, the bound
 * on the header ({@see TagSet}: 200 tags / 4 KiB) and the overflow tags that
 * keep a truncated page purgeable.
 *
 * Every cacheable page carries {@see ALL}; a purge of it refreshes the whole
 * shop (and needs a confirmation on the admin screen).
 */
final class TagPolicy
{
    public const ALL = 'all';
    public const OVERFLOW = 'tag_overflow';

    /**
     * @param array<string, string> $families        regex on the unprefixed tag => its overflow tag
     * @param list<string>          $identityPrefixes tags that name the page itself (kept first when the header is full)
     */
    public function __construct(
        private readonly string $prefix = '',
        private readonly array $families = [],
        private readonly array $identityPrefixes = [],
    ) {
    }

    public function withPrefix(string $prefix): self
    {
        return new self($prefix, $this->families, $this->identityPrefixes);
    }

    /**
     * @param iterable<string> $tags
     */
    public function headerValue(iterable $tags): string
    {
        $set = new TagSet($this->prefix, self::OVERFLOW, TagSet::DEFAULT_MAX_TAGS, TagSet::DEFAULT_MAX_BYTES, $this->families);
        $set->add(self::ALL, TagSet::IDENTITY);
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            $set->add($tag, $this->isIdentity($tag) ? TagSet::IDENTITY : TagSet::REFERENCE);
        }

        return $set->headerValue(',');
    }

    /**
     * The tags a purge sends: normalised and prefixed, plus the overflow tags
     * of the kinds it carries.
     *
     * @param iterable<string> $tags unprefixed
     * @return list<string>
     */
    public function purgeTags(iterable $tags): array
    {
        $out = [];
        $raw = [];
        foreach ($tags as $tag) {
            // A blank tag is no tag: normalised it would be the bare prefix,
            // which names nothing (and would drag the overflow tag along).
            if (trim((string) $tag) === '') {
                continue;
            }
            $normalised = TagSet::normalise($this->prefix, (string) $tag);
            if ($normalised !== '' && $normalised !== $this->prefix) {
                $out[$normalised] = true;
                $raw[] = (string) $tag;
            }
        }
        if ($out === []) {
            return [];
        }
        foreach (TagSet::overflowTagsFor($raw, $this->prefix, self::OVERFLOW, $this->families) as $overflow) {
            $out[$overflow] = true;
        }

        return array_map('strval', array_keys($out));
    }

    public function allTag(): string
    {
        return TagSet::normalise($this->prefix, self::ALL);
    }

    private function isIdentity(string $tag): bool
    {
        foreach ($this->identityPrefixes as $prefix) {
            if (str_starts_with($tag, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
