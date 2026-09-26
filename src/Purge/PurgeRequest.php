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

    public const MODE_SOFT = 'soft';
    public const MODE_HARD = 'hard';

    private string $type;
    private ?string $url = null;
    private ?string $tag = null;
    /** @var array<string> */
    private array $tags = [];
    private string $matchMode = self::MATCH_ANY;
    private ?string $pattern = null;
    private string $patternType = self::PATTERN_WILDCARD;
    private bool $soft = false;
    /** soft() or hard() was called: only then does toEngineBody() send `mode`. */
    private bool $modeSet = false;
    /** @var array<string> */
    private array $excludeTags = [];

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
        $this->modeSet = true;

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
     * Remove at once. Only an explicit soft() or hard() puts `mode` in the
     * engine body; without either the operator's admin.default_purge_mode
     * applies (the engine's default is soft).
     */
    public function hard(): self
    {
        $this->soft = false;
        $this->modeSet = true;

        return $this;
    }

    /**
     * Leave entries that ALSO carry one of these tags alone (tags purges only;
     * the engine's `exclude_tags`).
     *
     * @param array<string> $tags
     */
    public function excluding(array $tags): self
    {
        if ($this->type !== self::TYPE_TAGS) {
            throw new \LogicException('exclude_tags applies to a tags purge only.');
        }
        $this->excludeTags = array_values(array_map('strval', $tags));

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getExcludeTags(): array
    {
        return $this->excludeTags;
    }

    /**
     * `soft` when soft() was called, otherwise `hard` — the 1.4.1 answer. It
     * does not say whether a mode will be SENT: see hasExplicitMode().
     */
    public function getMode(): string
    {
        return $this->soft ? self::MODE_SOFT : self::MODE_HARD;
    }

    /** Whether soft() or hard() was called, i.e. whether toEngineBody() sends `mode`. */
    public function hasExplicitMode(): bool
    {
        return $this->modeSet;
    }

    /**
     * The admin API endpoint this purge is sent to (1.5.0).
     */
    public function engineEndpoint(): string
    {
        return match ($this->type) {
            self::TYPE_URL => '/admin/purge/url',
            self::TYPE_TAG => '/admin/purge/tag',
            self::TYPE_TAGS => '/admin/purge/tags',
            self::TYPE_PATTERN => '/admin/purge/tag/pattern',
            self::TYPE_ALL => '/admin/cache/clear',
            default => throw new \LogicException('A ban is not a purge: use TridentClient::createBan().'),
        };
    }

    /**
     * The request body the engine reads for this purge (1.5.0).
     *
     * Unlike toArray() (kept as it was for compatibility), this is exactly
     * the engine's schema: an absolute URL is split into path, host and
     * scheme, which the engine keys on separately; a tag pattern sends
     * `pattern_type`. `mode` is sent only after soft() or hard(): otherwise
     * the operator's admin.default_purge_mode decides (engine default: soft).
     * A full clear takes no mode — the engine has none.
     *
     * @return array<string, mixed>
     */
    public function toEngineBody(): array
    {
        $mode = $this->modeSet ? ['mode' => $this->getMode()] : [];
        return match ($this->type) {
            self::TYPE_URL => \Qoliber\Trident\Admin\SiteUrl::parse((string) $this->url)->fields() + $mode,
            self::TYPE_TAG => ['tag' => $this->tag] + $mode,
            self::TYPE_TAGS => [
                'tags' => array_values($this->tags),
                'match_mode' => $this->matchMode,
            ] + $mode + ($this->excludeTags !== [] ? ['exclude_tags' => $this->excludeTags] : []),
            self::TYPE_PATTERN => [
                'pattern' => $this->pattern,
                'pattern_type' => $this->patternType,
            ] + $mode,
            self::TYPE_ALL => ['confirm' => true],
            default => throw new \LogicException('A ban is not a purge: use TridentClient::createBan().'),
        };
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
