<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Cache;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, string>
 */
class TagCollection implements Countable, IteratorAggregate
{
    /** @var array<string> */
    private array $tags = [];

    private string $separator = ',';

    public function __construct(array $tags = [], string $separator = ',')
    {
        $this->separator = $separator;
        foreach ($tags as $tag) {
            $this->add($tag);
        }
    }

    public static function create(): self
    {
        return new self();
    }

    public static function fromHeader(string $headerValue, string $separator = ','): self
    {
        $tags = array_map('trim', explode($separator, $headerValue));
        $tags = array_filter($tags, fn(string $tag) => $tag !== '');

        return new self($tags, $separator);
    }

    public function add(string $tag): self
    {
        $tag = trim($tag);
        if ($tag !== '' && !in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function addProduct(int|string $id): self
    {
        return $this->add("product.{$id}");
    }

    public function addCategory(int|string $id): self
    {
        return $this->add("category.{$id}");
    }

    public function addPage(int|string $id): self
    {
        return $this->add("page.{$id}");
    }

    public function addCustom(string $prefix, int|string $id): self
    {
        return $this->add("{$prefix}.{$id}");
    }

    public function remove(string $tag): self
    {
        $this->tags = array_values(array_filter(
            $this->tags,
            fn(string $t) => $t !== $tag
        ));

        return $this;
    }

    public function has(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function clear(): self
    {
        $this->tags = [];

        return $this;
    }

    public function merge(TagCollection $other): self
    {
        foreach ($other->all() as $tag) {
            $this->add($tag);
        }

        return $this;
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        return $this->tags;
    }

    public function toHeader(): string
    {
        return implode($this->separator, $this->tags);
    }

    public function count(): int
    {
        return count($this->tags);
    }

    public function isEmpty(): bool
    {
        return count($this->tags) === 0;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tags);
    }

    public function __toString(): string
    {
        return $this->toHeader();
    }
}
