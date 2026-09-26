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
use Qoliber\Trident\Cache\TagResolver;

class TagResolverTest extends TestCase
{
    private TagResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TagResolver();
    }

    public function testResolveWithScalarId(): void
    {
        $tags = $this->resolver->resolve('product', 123);

        $this->assertEquals(['product.123'], $tags);
    }

    public function testResolveWithArrayEntity(): void
    {
        $entity = ['id' => 456, 'name' => 'Test Product'];
        $tags = $this->resolver->resolve('product', $entity);

        $this->assertEquals(['product.456'], $tags);
    }

    public function testResolveWithObjectEntity(): void
    {
        $entity = new class {
            public function getId(): int
            {
                return 789;
            }
        };

        $tags = $this->resolver->resolve('product', $entity);

        $this->assertEquals(['product.789'], $tags);
    }

    public function testResolveWithObjectProperty(): void
    {
        $entity = new class {
            public int $id = 999;
        };

        $tags = $this->resolver->resolve('category', $entity);

        $this->assertEquals(['category.999'], $tags);
    }

    public function testResolveWithCustomResolver(): void
    {
        $this->resolver->register('product', function ($entity) {
            return [
                "product.{$entity['id']}",
                "category.{$entity['category_id']}",
            ];
        });

        $entity = ['id' => 1, 'category_id' => 5];
        $tags = $this->resolver->resolve('product', $entity);

        $this->assertEquals(['product.1', 'category.5'], $tags);
    }

    public function testResolveManyEntities(): void
    {
        $entities = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        $tags = $this->resolver->resolveMany('product', $entities);

        $this->assertEquals(['product.1', 'product.2', 'product.3'], $tags);
    }

    public function testResolveManyDeduplicates(): void
    {
        $this->resolver->register('product', function ($entity) {
            return [
                "product.{$entity['id']}",
                "store.default",
            ];
        });

        $entities = [
            ['id' => 1],
            ['id' => 2],
        ];

        $tags = $this->resolver->resolveMany('product', $entities);

        $this->assertCount(3, $tags);
        $this->assertContains('store.default', $tags);
    }

    public function testResolveWithNoIdReturnsListTag(): void
    {
        $entity = new \stdClass();
        $tags = $this->resolver->resolve('product', $entity);

        $this->assertEquals(['product.list'], $tags);
    }
}
