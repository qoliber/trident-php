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

use Qoliber\Trident\Admin\Payload;

/**
 * The engine's full answer, kept next to the typed fields (1.5.0).
 *
 * A typed response carries the fields this library knows about. The engine
 * gains fields between releases, and a screen sometimes needs one no property
 * exists for yet — before 1.5.0 the only way to it was the lower-level
 * Admin\Api. Every typed response now keeps the decoded answer it was built
 * from: `raw()` returns it as-is, `payload()` wraps it in the same dot-path,
 * typed accessors the untyped endpoints return.
 */
trait CarriesRaw
{
    /** @var array<array-key, mixed> */
    private array $raw = [];

    /**
     * The decoded answer this response was built from, including fields no
     * property exists for. Empty when the object was constructed directly.
     *
     * @return array<array-key, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /** The same answer with typed, dot-path accessors (`int('snapshot.allocator.rss_bytes')`). */
    public function payload(): Payload
    {
        return new Payload($this->raw);
    }

    /**
     * @internal Called by fromArray() only.
     * @param array<array-key, mixed> $raw
     */
    private function attachRaw(array $raw): static
    {
        $this->raw = $raw;
        return $this;
    }
}
