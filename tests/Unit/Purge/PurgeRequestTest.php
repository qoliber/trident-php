<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Purge;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Purge\PurgeRequest;

class PurgeRequestTest extends TestCase
{
    public function testUrlRequest(): void
    {
        $request = PurgeRequest::url('/products/123');

        $this->assertEquals(PurgeRequest::TYPE_URL, $request->getType());
        $this->assertEquals('/products/123', $request->getUrl());
        $this->assertFalse($request->isSoft());
    }

    public function testUrlRequestWithSoftPurge(): void
    {
        $request = PurgeRequest::url('/products/123')->soft();

        $this->assertEquals(PurgeRequest::TYPE_URL, $request->getType());
        $this->assertEquals('/products/123', $request->getUrl());
        $this->assertTrue($request->isSoft());
    }

    public function testTagRequest(): void
    {
        $request = PurgeRequest::tag('product.123');

        $this->assertEquals(PurgeRequest::TYPE_TAG, $request->getType());
        $this->assertEquals('product.123', $request->getTag());
    }

    public function testTagsRequest(): void
    {
        $tags = ['product.1', 'product.2', 'category.shoes'];
        $request = PurgeRequest::tags($tags);

        $this->assertEquals(PurgeRequest::TYPE_TAGS, $request->getType());
        $this->assertEquals($tags, $request->getTags());
        $this->assertEquals(PurgeRequest::MATCH_ANY, $request->getMatchMode());
    }

    public function testTagsRequestWithMatchAll(): void
    {
        $tags = ['category.shoes', 'store.default'];
        $request = PurgeRequest::tags($tags)->matchAll();

        $this->assertEquals(PurgeRequest::TYPE_TAGS, $request->getType());
        $this->assertEquals($tags, $request->getTags());
        $this->assertEquals(PurgeRequest::MATCH_ALL, $request->getMatchMode());
    }

    public function testTagsRequestWithMatchAny(): void
    {
        $tags = ['product.1', 'product.2'];
        $request = PurgeRequest::tags($tags)->matchAll()->matchAny();

        $this->assertEquals(PurgeRequest::MATCH_ANY, $request->getMatchMode());
    }

    public function testPatternRequestWithWildcard(): void
    {
        $request = PurgeRequest::pattern('product.*');

        $this->assertEquals(PurgeRequest::TYPE_PATTERN, $request->getType());
        $this->assertEquals('product.*', $request->getPattern());
        $this->assertEquals(PurgeRequest::PATTERN_WILDCARD, $request->getPatternType());
    }

    public function testPatternRequestWithRegex(): void
    {
        $request = PurgeRequest::pattern('^product\\.[0-9]+$', PurgeRequest::PATTERN_REGEX);

        $this->assertEquals(PurgeRequest::TYPE_PATTERN, $request->getType());
        $this->assertEquals('^product\\.[0-9]+$', $request->getPattern());
        $this->assertEquals(PurgeRequest::PATTERN_REGEX, $request->getPatternType());
    }

    public function testAllRequest(): void
    {
        $request = PurgeRequest::all();

        $this->assertEquals(PurgeRequest::TYPE_ALL, $request->getType());
    }

    public function testBanRequest(): void
    {
        $request = PurgeRequest::ban('^/admin/.*');

        $this->assertEquals(PurgeRequest::TYPE_BAN, $request->getType());
        $this->assertEquals('^/admin/.*', $request->getPattern());
    }

    public function testToArrayForUrlRequest(): void
    {
        $request = PurgeRequest::url('/products/123')->soft();

        $array = $request->toArray();

        $this->assertEquals('/products/123', $array['url']);
        $this->assertTrue($array['soft']);
    }

    public function testToArrayForTagRequest(): void
    {
        $request = PurgeRequest::tag('product.123');

        $array = $request->toArray();

        $this->assertEquals('product.123', $array['tag']);
    }

    public function testToArrayForTagsRequestWithAny(): void
    {
        $tags = ['product.1', 'product.2'];
        $request = PurgeRequest::tags($tags);

        $array = $request->toArray();

        $this->assertEquals($tags, $array['tags']);
        $this->assertEquals('any', $array['match_mode']);
    }

    public function testToArrayForTagsRequestWithAll(): void
    {
        $tags = ['category.shoes', 'store.default'];
        $request = PurgeRequest::tags($tags)->matchAll();

        $array = $request->toArray();

        $this->assertEquals($tags, $array['tags']);
        $this->assertEquals('all', $array['match_mode']);
    }

    public function testToArrayForPatternRequest(): void
    {
        $request = PurgeRequest::pattern('product.*', PurgeRequest::PATTERN_WILDCARD);

        $array = $request->toArray();

        $this->assertEquals('product.*', $array['pattern']);
        $this->assertEquals('wildcard', $array['pattern_type']);
    }

    public function testToArrayForBanRequest(): void
    {
        $request = PurgeRequest::ban('^/api/.*');

        $array = $request->toArray();

        $this->assertEquals('^/api/.*', $array['pattern']);
    }

    public function testToArrayForAllRequest(): void
    {
        $request = PurgeRequest::all();

        $array = $request->toArray();

        $this->assertEmpty($array);
    }

    public function testFluentInterface(): void
    {
        $request = PurgeRequest::tags(['product.1', 'category.shoes'])
            ->matchAll()
            ->soft();

        $this->assertEquals(PurgeRequest::TYPE_TAGS, $request->getType());
        $this->assertEquals(PurgeRequest::MATCH_ALL, $request->getMatchMode());
        $this->assertTrue($request->isSoft());
    }

    public function testConstants(): void
    {
        $this->assertEquals('url', PurgeRequest::TYPE_URL);
        $this->assertEquals('tag', PurgeRequest::TYPE_TAG);
        $this->assertEquals('tags', PurgeRequest::TYPE_TAGS);
        $this->assertEquals('pattern', PurgeRequest::TYPE_PATTERN);
        $this->assertEquals('all', PurgeRequest::TYPE_ALL);
        $this->assertEquals('ban', PurgeRequest::TYPE_BAN);
        $this->assertEquals('any', PurgeRequest::MATCH_ANY);
        $this->assertEquals('all', PurgeRequest::MATCH_ALL);
        $this->assertEquals('wildcard', PurgeRequest::PATTERN_WILDCARD);
        $this->assertEquals('regex', PurgeRequest::PATTERN_REGEX);
    }
}
