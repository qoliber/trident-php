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

/**
 * toEngineBody() is exactly the admin API's request schema (1.5.0), with the
 * purge mode always stated so the engine's own default never applies by
 * accident. toArray() keeps its 1.4 shape (see PurgeRequestTest).
 */
final class PurgeRequestEngineBodyTest extends TestCase
{
    public function testAnAbsoluteUrlIsSplitAndTheModeIsStated(): void
    {
        $r = PurgeRequest::url('https://shop.example.com/sale/?a=1');
        self::assertSame('/admin/purge/url', $r->engineEndpoint());
        self::assertSame(
            ['url' => '/sale/?a=1', 'host' => 'shop.example.com', 'scheme' => 'https'],
            $r->toEngineBody()
        );
        self::assertSame('soft', $r->soft()->toEngineBody()['mode']);
    }

    public function testATagCarriesItsMode(): void
    {
        self::assertSame(['tag' => 'cat_5'], PurgeRequest::tag('cat_5')->toEngineBody());
        self::assertSame(['tag' => 'cat_5', 'mode' => 'hard'], PurgeRequest::tag('cat_5')->hard()->toEngineBody());
        self::assertSame('/admin/purge/tag', PurgeRequest::tag('x')->engineEndpoint());
    }

    public function testTagsCarryMatchModeModeAndExclusions(): void
    {
        $r = PurgeRequest::tags(['cat_5', 'cat_6'])->matchAll()->excluding(['home'])->soft();
        self::assertSame('/admin/purge/tags', $r->engineEndpoint());
        self::assertSame(
            ['tags' => ['cat_5', 'cat_6'], 'match_mode' => 'all', 'mode' => 'soft', 'exclude_tags' => ['home']],
            $r->toEngineBody()
        );
        self::assertArrayNotHasKey('exclude_tags', PurgeRequest::tags(['a'])->toEngineBody(), 'omitted when empty');
    }

    public function testATagPatternSendsPatternType(): void
    {
        $r = PurgeRequest::pattern('^cat_\d+$', PurgeRequest::PATTERN_REGEX);
        self::assertSame('/admin/purge/tag/pattern', $r->engineEndpoint());
        self::assertSame(['pattern' => '^cat_\d+$', 'pattern_type' => 'regex'], $r->toEngineBody());
    }

    public function testAClearHasNoMode(): void
    {
        self::assertSame('/admin/cache/clear', PurgeRequest::all()->engineEndpoint());
        self::assertSame(['confirm' => true], PurgeRequest::all()->soft()->toEngineBody());
    }

    public function testHardUndoesSoft(): void
    {
        self::assertSame('hard', PurgeRequest::tag('x')->soft()->hard()->getMode());
    }

    public function testABanIsNotAPurge(): void
    {
        $this->expectException(\LogicException::class);
        PurgeRequest::ban('/old/*')->toEngineBody();
    }

    public function testExclusionsApplyToATagsPurgeOnly(): void
    {
        $this->expectException(\LogicException::class);
        PurgeRequest::tag('x')->excluding(['y']);
    }
}
