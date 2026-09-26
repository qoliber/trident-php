<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Cache\TagCollection;

class TagCollectionTest extends TestCase
{
    public function testCreateEmptyCollection(): void
    {
        $tags = new TagCollection();

        $this->assertCount(0, $tags);
        $this->assertTrue($tags->isEmpty());
    }

    public function testCreateWithTags(): void
    {
        $tags = new TagCollection(['product.1', 'category.2']);

        $this->assertCount(2, $tags);
        $this->assertFalse($tags->isEmpty());
    }

    public function testAddTag(): void
    {
        $tags = TagCollection::create()
            ->add('product.1')
            ->add('category.2');

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->has('product.1'));
        $this->assertTrue($tags->has('category.2'));
    }

    public function testAddDuplicateTagIsIgnored(): void
    {
        $tags = TagCollection::create()
            ->add('product.1')
            ->add('product.1')
            ->add('product.1');

        $this->assertCount(1, $tags);
    }

    public function testAddEmptyTagIsIgnored(): void
    {
        $tags = TagCollection::create()
            ->add('')
            ->add('   ')
            ->add('product.1');

        $this->assertCount(1, $tags);
    }

    public function testRemoveTag(): void
    {
        $tags = TagCollection::create()
            ->add('product.1')
            ->add('category.2')
            ->remove('product.1');

        $this->assertCount(1, $tags);
        $this->assertFalse($tags->has('product.1'));
        $this->assertTrue($tags->has('category.2'));
    }

    public function testClear(): void
    {
        $tags = TagCollection::create()
            ->add('product.1')
            ->add('category.2')
            ->clear();

        $this->assertCount(0, $tags);
        $this->assertTrue($tags->isEmpty());
    }

    public function testMerge(): void
    {
        $tags1 = new TagCollection(['product.1', 'product.2']);
        $tags2 = new TagCollection(['category.1', 'product.1']);

        $tags1->merge($tags2);

        $this->assertCount(3, $tags1);
        $this->assertTrue($tags1->has('product.1'));
        $this->assertTrue($tags1->has('product.2'));
        $this->assertTrue($tags1->has('category.1'));
    }

    public function testToHeader(): void
    {
        $tags = new TagCollection(['product.1', 'category.2']);

        $this->assertEquals('product.1,category.2', $tags->toHeader());
    }

    public function testToHeaderWithCustomSeparator(): void
    {
        $tags = new TagCollection(['product.1', 'category.2'], ' ');

        $this->assertEquals('product.1 category.2', $tags->toHeader());
    }

    public function testFromHeader(): void
    {
        $tags = TagCollection::fromHeader('product.1, category.2, page.3');

        $this->assertCount(3, $tags);
        $this->assertTrue($tags->has('product.1'));
        $this->assertTrue($tags->has('category.2'));
        $this->assertTrue($tags->has('page.3'));
    }

    public function testFromHeaderWithCustomSeparator(): void
    {
        $tags = TagCollection::fromHeader('product.1 category.2 page.3', ' ');

        $this->assertCount(3, $tags);
    }

    public function testConvenienceMethods(): void
    {
        $tags = TagCollection::create()
            ->addProduct(123)
            ->addCategory(45)
            ->addPage('home')
            ->addCustom('block', 'sidebar');

        $this->assertTrue($tags->has('product.123'));
        $this->assertTrue($tags->has('category.45'));
        $this->assertTrue($tags->has('page.home'));
        $this->assertTrue($tags->has('block.sidebar'));
    }

    public function testIterable(): void
    {
        $tags = new TagCollection(['a', 'b', 'c']);
        $result = [];

        foreach ($tags as $tag) {
            $result[] = $tag;
        }

        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testToString(): void
    {
        $tags = new TagCollection(['product.1', 'category.2']);

        $this->assertEquals('product.1,category.2', (string) $tags);
    }
}
