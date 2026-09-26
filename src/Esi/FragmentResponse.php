<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Esi;

use Qoliber\Trident\Tags\TagSet;

/**
 * Response headers for fragment endpoints.
 */
final class FragmentResponse
{
    /**
     * A SHARED fragment — the same for every visitor (a menu, a footer): one
     * cache entry with its own TTL and tags, so a change purges that entry and
     * every page picks it up on its next assembly.
     *
     * @return array<string, string>
     */
    public static function shared(int $ttl, TagSet $tags, string $tagHeader = 'X-Cache-Tags'): array
    {
        $headers = ['Cache-Control' => sprintf('public, max-age=0, s-maxage=%d', max(1, $ttl))];
        if (!$tags->isEmpty()) {
            $headers[$tagHeader] = $tags->headerValue();
        }
        $headers['X-Robots-Tag'] = 'noindex';
        return $headers;
    }

    /**
     * A PRIVATE fragment — a per-request token (a nonce): never stored. In
     * Trident `hole_punch` mode it is fetched on every assembly; in `assemble`
     * mode it makes the assembled page uncacheable (the engine propagates the
     * fragment's cacheability), so a platform should use private fragments in
     * hole_punch mode only.
     *
     * @return array<string, string>
     */
    public static function private(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex',
        ];
    }
}
