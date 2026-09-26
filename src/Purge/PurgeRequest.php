<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Purge;

/**
 * Fluent builder for purge requests
 */
class PurgeRequest
{
    public const TYPE_URL = 'url';
    public const TYPE_TAG = 'tag';
    public const TYPE_TAGS = 'tags';
    public const TYPE_PATTERN = 'pattern';
    public const TYPE_ALL = 'all';
    public const TYPE_BAN = 'ban';

    public const MATCH_ANY = 'any';
    public const MATCH_ALL = 'all';

    public const PATTERN_WILDCARD = 'wildcard';
    public const PATTERN_REGEX = 'regex';

    private string $type;
    private ?string $url = null;
    private ?string $tag = null;
    /** @var array<string> */
    private array $tags = [];
    private string $matchMode = self::MATCH_ANY;
    private ?string $pattern = null;
    private string $patternType = self::PATTERN_WILDCARD;
    private bool $soft = false;

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function url(string $url): self
    {
        $request = new self(self::TYPE_URL);
        $request->url = $url;

        return $request;
    }

    public static function tag(string $tag): self
    {
        $request = new self(self::TYPE_TAG);
        $request->tag = $tag;

        return $request;
    }

    /**
     * @param array<string> $tags
     */
    public static function tags(array $tags): self
    {
        $request = new self(self::TYPE_TAGS);
        $request->tags = $tags;

        return $request;
    }

    public static function pattern(string $pattern, string $patternType = self::PATTERN_WILDCARD): self
    {
        $request = new self(self::TYPE_PATTERN);
        $request->pattern = $pattern;
        $request->patternType = $patternType;

        return $request;
    }

    public static function all(): self
    {
        return new self(self::TYPE_ALL);
    }

    public static function ban(string $pattern): self
    {
        $request = new self(self::TYPE_BAN);
        $request->pattern = $pattern;

        return $request;
    }

    public function soft(): self
    {
        $this->soft = true;

        return $this;
    }

    public function matchAll(): self
    {
        $this->matchMode = self::MATCH_ALL;

        return $this;
    }

    public function matchAny(): self
    {
        $this->matchMode = self::MATCH_ANY;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getMatchMode(): string
    {
        return $this->matchMode;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getPatternType(): string
    {
        return $this->patternType;
    }

    public function isSoft(): bool
    {
        return $this->soft;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return match ($this->type) {
            self::TYPE_URL => [
                'url' => $this->url,
                'soft' => $this->soft,
            ],
            self::TYPE_TAG => [
                'tag' => $this->tag,
            ],
            self::TYPE_TAGS => [
                'tags' => $this->tags,
                'match_mode' => $this->matchMode,
            ],
            self::TYPE_PATTERN => [
                'pattern' => $this->pattern,
                'pattern_type' => $this->patternType,
            ],
            self::TYPE_BAN => [
                'pattern' => $this->pattern,
            ],
            default => [],
        };
    }
}
