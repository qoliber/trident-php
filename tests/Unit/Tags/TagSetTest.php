<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Tags;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Tags\TagSet;

final class TagSetTest extends TestCase
{
    public function testNormaliseMakesTagsSeparatorSafe(): void
    {
        self::assertSame('p_1', TagSet::normalise('', 'P_1'));
        self::assertSame('a_b_c', TagSet::normalise('', 'a,b c'));
        self::assertSame('shop1_all', TagSet::normalise('shop1_', 'all'));
        self::assertSame('', TagSet::normalise('', ' ,, '));
        self::assertSame(TagSet::MAX_TAG_LENGTH, strlen(TagSet::normalise('', str_repeat('x', 500))));
    }

    public function testPriorityOrderAndDedup(): void
    {
        $set = new TagSet();
        $set->add('p_2', TagSet::LISTED);
        $set->add('term_7', TagSet::REFERENCE);
        $set->add('all');
        $set->add('p_2');
        $set->add('p_2', TagSet::LISTED);
        self::assertSame(['all', 'p_2', 'term_7'], $set->toArray());
        self::assertSame('all,p_2,term_7', $set->headerValue());
        self::assertSame('all p_2 term_7', $set->headerValue(' '));
        self::assertFalse($set->isEmpty());
        self::assertTrue((new TagSet())->isEmpty());
    }

    public function testBoundedByTagCountDroppingListedFirstWithOverflowTag(): void
    {
        $set = new TagSet('', 'list_overflow', 10);
        $set->add('all');
        $set->add('cat_5');
        $set->add('menu', TagSet::REFERENCE);
        foreach (range(1, 50) as $id) {
            $set->add('p_' . $id, TagSet::LISTED);
        }
        $tags = $set->toArray();
        self::assertCount(10, $tags);
        self::assertSame(['all', 'cat_5', 'menu'], array_slice($tags, 0, 3));
        self::assertSame('list_overflow', end($tags));
        self::assertSame(['p_1', 'p_2', 'p_3', 'p_4', 'p_5', 'p_6'], array_slice($tags, 3, 6));
    }

    public function testDefaultBudgetIsTridentDefaultOf200(): void
    {
        $set = new TagSet();
        foreach (range(1, 1000) as $id) {
            $set->add('p_' . $id, TagSet::LISTED);
        }
        self::assertCount(200, $set->toArray());
    }

    public function testBoundedByHeaderBytes(): void
    {
        $set = new TagSet('', 'list_overflow', 200, 100);
        foreach (range(1, 40) as $id) {
            $set->add('p_' . $id, TagSet::LISTED);
        }
        self::assertLessThanOrEqual(100, strlen($set->headerValue()));
        self::assertStringEndsWith('list_overflow', $set->headerValue());
    }

    public function testNoOverflowTagWhenEverythingFitsUnlessAddedExplicitly(): void
    {
        $set = new TagSet();
        $set->addAll(['all', 'p_1']);
        self::assertNotContains('list_overflow', $set->toArray());
        $set->add('list_overflow');
        self::assertSame(['all', 'p_1', 'list_overflow'], $set->toArray());
    }

    public function testPrefixIsAppliedToOverflowToo(): void
    {
        $set = new TagSet('s1_', 'list_overflow', 3);
        $set->addAll(['a', 'b', 'c', 'd']);
        self::assertSame(['s1_a', 's1_b', 's1_list_overflow'], $set->toArray());
    }
}
