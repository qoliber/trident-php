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

/**
 * Whether a shared cache may store a response, why, and the Cache-Control to send.
 */
final class Decision
{
    private function __construct(
        public readonly bool $cacheable,
        public readonly string $reason,
        public readonly string $cacheControl
    ) {
    }

    /**
     * `max-age=0` for browsers: the HTML is shared, and a browser keeping its own
     * copy would show a stale page after a purge. `s-maxage` is Trident's.
     */
    public static function cacheable(int $ttl, int $swr): self
    {
        $cc = sprintf('public, max-age=0, s-maxage=%d', max(1, $ttl));
        if ($swr > 0) {
            $cc .= sprintf(', stale-while-revalidate=%d', $swr);
        }
        return new self(true, 'cacheable', $cc);
    }

    public static function privateNoStore(string $reason): self
    {
        return new self(false, $reason, 'private, no-store, no-cache, must-revalidate, max-age=0');
    }
}
