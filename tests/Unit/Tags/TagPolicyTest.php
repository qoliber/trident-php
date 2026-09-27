<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Tags;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Tags\TagPolicy;

/**
 * Tag names on both sides — the header and the purge — so they always match
 * (1.8.0).
 */
final class TagPolicyTest extends TestCase
{
    public function testAPurgeCarriesTheOverflowOfTheKindsItNames(): void
    {
        $p = new TagPolicy('shop_', ['/^p_\d+$/' => 'p_overflow']);
        self::assertSame(['shop_p_1', 'shop_p_overflow'], $p->purgeTags(['p_1']));
        self::assertSame(['shop_t_1', 'shop_tag_overflow'], $p->purgeTags(['t_1']));
        self::assertSame([], $p->purgeTags(['', '  ']));
        self::assertSame('shop_all', $p->allTag());
    }

    public function testIdentityTagsSurviveAFullHeaderAndOverflowIsNamed(): void
    {
        $refs = array_map(static fn (int $i): string => 'p_' . $i, range(1, 400));
        $p = new TagPolicy('', ['/^p_\d+$/' => 'p_overflow'], ['pp_']);
        $header = explode(',', $p->headerValue(array_merge($refs, ['pp_7'])));
        self::assertContains('pp_7', $header, 'the page itself is never dropped');
        self::assertContains('all', $header);
        self::assertContains('p_overflow', $header, 'a truncated page is still purged by its kind');
        self::assertLessThanOrEqual(200, \count($header));
    }

    public function testWithPrefixKeepsTheRules(): void
    {
        $p = (new TagPolicy('', ['/^p_\d+$/' => 'p_overflow'], ['pp_']))->withPrefix('s2_');
        self::assertSame(['s2_p_1', 's2_p_overflow'], $p->purgeTags(['p_1']));
        self::assertSame('s2_all', $p->allTag());
    }
}
