<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Response;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Response\PurgeResponse;

/**
 * The client's purge methods say whether Trident acknowledged the purge
 * (Delivery\Acknowledgement), not only that a body came back.
 */
final class PurgeResponseAcknowledgementTest extends TestCase
{
    private static function client(int $status, string $body): TridentClient
    {
        $http = new class ($status, $body) implements ClientInterface {
            public function __construct(private int $status, private string $body)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response($this->status, [], $this->body);
            }
        };
        $factory = new HttpFactory();
        return new TridentClient('http://edge:9301', 'tok', $http, $factory, $factory);
    }

    public function testPurgeAcknowledged(): void
    {
        $r = self::client(200, '{"purged":4,"mode":"soft","state":"applied","barrier":"freshness"}')->purgeTags(['a']);
        self::assertTrue($r->isAcknowledged());
        self::assertSame('applied', $r->state);
        self::assertNull($r->failure);
        self::assertSame(4, $r->getPurgedCount());
    }

    public function testOlderEngineWithoutStateIsAcknowledged(): void
    {
        self::assertTrue(self::client(200, '{"purged":0,"mode":"soft","queued_refresh":0}')->purgeTag('a')->isAcknowledged());
    }

    public function testErrorObjectWithA200IsNotAPurge(): void
    {
        $r = self::client(200, '{"error":"queue full"}')->purgeTags(['a']);
        self::assertTrue($r->isSuccess(), 'the old flag stays true, for compatibility');
        self::assertFalse($r->isAcknowledged());
        self::assertSame('HTTP 200 — queue full', $r->failure);
    }

    public function testRefusedIsNotAPurge(): void
    {
        $r = self::client(200, '{"purged":0,"mode":"soft","state":"refused"}')->purgeTags(['a']);
        self::assertFalse($r->isAcknowledged());
        self::assertSame('refused', $r->state);
    }

    public function testEmptyBodyIsNotAPurge(): void
    {
        self::assertFalse(self::client(200, '')->purgeTags(['a'])->isAcknowledged());
    }

    public function testCacheClearUsesTheClearSchema(): void
    {
        self::assertTrue(self::client(200, '{"cleared":true,"entries_removed":7,"bytes_freed":100}')->purgeAll()->isAcknowledged());
        self::assertFalse(self::client(200, '{"cleared":false}')->purgeAll()->isAcknowledged());
    }

    public function testFromArrayWithoutStatusStaysCompatible(): void
    {
        self::assertTrue(PurgeResponse::fromArray(['purged' => 1, 'mode' => 'hard'])->isAcknowledged());
        self::assertFalse(PurgeResponse::fromArray(['purged' => 1, 'mode' => 'hard'], 202)->isAcknowledged());
    }
}
