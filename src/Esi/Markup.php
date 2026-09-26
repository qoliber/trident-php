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

/**
 * ESI markup with a built-in fallback.
 *
 *     <!--esi <esi:include src="…"/> --><esi:remove>…inline…</esi:remove>
 *
 * The ESI 1.0 fallback pattern: an ESI processor (Trident) removes
 * `<esi:remove>` and un-comments the include; anything else — the page reached
 * without Trident, Trident with `[esi]` off, a browser — shows the inline copy
 * and ignores the comment. Trident implements include, remove and the comment
 * markers, not `<esi:try>`, so a fragment that fails with no stale copy renders
 * empty; the fragment is cached with the page's grace to keep that rare.
 *
 * Rendering the inline copy on the page also keeps the page's side effects of
 * the block (enqueued scripts, client-side state) where the browser needs them.
 */
final class Markup
{
    /**
     * @param string $src      Root-relative fragment URL, e.g. `/?trident-esi=menu&…`.
     * @param string $fallback Inline HTML shown where no ESI processor runs.
     * @throws \InvalidArgumentException When `$src` could break out of the attribute or comment.
     */
    public static function includeWithFallback(string $src, string $fallback): string
    {
        self::assertSafeSrc($src);
        return '<!--esi <esi:include src="' . $src . '"/> --><esi:remove>' . $fallback . '</esi:remove>';
    }

    /**
     * ESI processors do not decode HTML entities in `src` (`&amp;` stays
     * `&amp;` and the fragment request carries a broken query), so the URL is
     * inserted verbatim — and must therefore consist of URL-safe characters only.
     */
    public static function assertSafeSrc(string $src): void
    {
        if (preg_match('#^/[A-Za-z0-9/_.~?=&%\-]*\z#', $src) !== 1) {
            throw new \InvalidArgumentException('ESI src must be a root-relative URL of URL-safe characters');
        }
    }

    /**
     * Whether markup still contains ESI syntax a browser must never see.
     */
    public static function containsRawEsi(string $html): bool
    {
        return preg_match('/<esi:|<!--esi/i', $html) === 1;
    }
}
