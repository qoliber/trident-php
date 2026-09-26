<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Exception\InvalidRequest;
use Qoliber\Trident\Exception\TridentException;
use Qoliber\Trident\Purge\PurgeRequest;
use Qoliber\Trident\Response\ProtectionStatsResponse;
use Qoliber\Trident\Testing\FakeTransport;

/**
 * Findings of the 1.5.0 review: 1.4.1 compatibility and the purge-mode default.
 */
final class ReviewFindingsTest extends TestCase
{
    private function client(FakeTransport $t): TridentClient
    {
        return TridentClient::forInstance(new Instance('edge', 'http://edge:9301', 'tok'), $t);
    }

    /** 1.4.1 kept the engine's name keys on `backends`; 1.5.0 must too. */
    public function testProtectionBackendsKeepTheirNameKeys(): void
    {
        $p = ProtectionStatsResponse::fromArray([
            'backends' => ['default' => ['acquired_total' => 1], 'api' => ['acquired_total' => 2]],
        ]);
        self::assertSame(['default', 'api'], array_keys($p->backends));
        self::assertSame('api', $p->backends['api']->name);
        self::assertSame(['default', 'api'], array_keys($p->toArray()['backends']), 'toArray() has the 1.4.1 shape');
    }

    /** An unset mode lets the operator's admin.default_purge_mode apply (the engine default is soft). */
    public function testADefaultRequestSendsNoMode(): void
    {
        foreach ([PurgeRequest::tag('a'), PurgeRequest::tags(['a']), PurgeRequest::pattern('a*'), PurgeRequest::url('/a')] as $r) {
            self::assertArrayNotHasKey('mode', $r->toEngineBody(), $r->getType());
            self::assertSame('hard', $r->getMode(), 'getMode() keeps its 1.4.1 answer');
        }
        self::assertSame('soft', PurgeRequest::tag('a')->soft()->toEngineBody()['mode']);
        self::assertSame('hard', PurgeRequest::tag('a')->hard()->toEngineBody()['mode']);
        self::assertSame(['url' => '/a', 'soft' => false], PurgeRequest::url('/a')->toArray(), 'toArray() is 1.4.1');
    }

    public function testPurgeUrlStillSendsItsExplicitMode(): void
    {
        $t = (new FakeTransport())->answer('edge', 200, '{"purged":1,"mode":"hard"}');
        $this->client($t)->purgeUrl('/a');
        self::assertSame('hard', json_decode((string) $t->requests[0]['body'], true)['mode']);
    }

    /** 1.4.1 callers catch TridentException; a rejected pin must still be one. */
    public function testAnInvalidPinIsATridentException(): void
    {
        $t = new FakeTransport();
        try {
            $this->client($t)->denoiserPathPin('gone');
            self::fail('expected an exception');
        } catch (TridentException $e) {
            self::assertInstanceOf(InvalidRequest::class, $e);
        }
        self::assertSame([], $t->requests);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function injectedCookies(): array
    {
        return [
            'semicolon in value' => [['a' => '1; admin=1']],
            'comma in value'     => [['a' => '1, b=2']],
            'newline in value'   => [['a' => "1\r\nX-Evil: 1"]],
            'semicolon in name'  => [['a;b' => '1']],
            'equals in name'     => [['a=b' => '1']],
        ];
    }

    /**
     * @param array<string, string> $cookies
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('injectedCookies')]
    public function testExplainRefusesACookieThatWouldInjectAnother(array $cookies): void
    {
        $t = new FakeTransport();
        try {
            $this->client($t)->explainRequest('http://a.example/', 'GET', [], $cookies);
            self::fail('expected InvalidRequest');
        } catch (InvalidRequest $e) {
            self::assertStringContainsString('Cookie', $e->getMessage());
        }
        self::assertSame([], $t->requests, 'nothing sent');
    }
}
