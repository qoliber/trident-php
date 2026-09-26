<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Acknowledgement;

final class AcknowledgementTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function acks(): array
    {
        return [
            '1.8 soft' => [200, '{"purged":3,"mode":"soft","state":"applied","barrier":"freshness","queued_refresh":3}'],
            '1.8 hard' => [200, '{"purged":0,"mode":"hard","state":"applied","barrier":"delivery"}'],
            '1.6/1.7 — no state field' => [200, '{"purged":0,"mode":"soft","queued_refresh":0}'],
            'recorded (reflect mode)' => [200, '{"purged":0,"mode":"soft","state":"recorded"}'],
        ];
    }

    #[DataProvider('acks')]
    public function testAcknowledged(int $status, string $body): void
    {
        self::assertNull(Acknowledgement::purgeFailure($status, $body));
        self::assertTrue(Acknowledgement::isPurgeAck((array) json_decode($body, true)));
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function failures(): array
    {
        return [
            '401 bad token' => [401, '{"error":"Unauthorized"}', 'HTTP 401 — Unauthorized'],
            '429 admin limiter' => [429, '{"error":"Too many requests"}', 'HTTP 429 — Too many requests'],
            '503' => [503, '', 'HTTP 503'],
            'proxy HTML error page' => [502, '<html>Bad gateway</html>', 'HTTP 502'],
            '200 but HTML (login page)' => [200, '<html>login</html>', 'HTTP 200 — response is not JSON'],
            '200 error object' => [200, '{"error":"queue full"}', 'HTTP 200 — queue full'],
            '200 wrong schema' => [200, '{"cleared":true}', 'HTTP 200 — not an acknowledgement'],
            'purged as string' => [200, '{"purged":"3","mode":"soft"}', 'HTTP 200 — not an acknowledgement'],
            'mode missing' => [200, '{"purged":3}', 'HTTP 200 — not an acknowledgement'],
            'state refused' => [200, '{"purged":0,"mode":"soft","state":"refused"}', 'HTTP 200 — purge refused'],
            'state not a string' => [200, '{"purged":0,"mode":"soft","state":1}', 'HTTP 200 — not an acknowledgement'],
            'no response' => [0, '', 'no response'],
        ];
    }

    #[DataProvider('failures')]
    public function testNotAcknowledged(int $status, string $body, string $reason): void
    {
        self::assertSame($reason, Acknowledgement::purgeFailure($status, $body));
    }

    /**
     * Reflect mode (trident-core admin/handlers/reflect.rs): the purge is
     * durably queued and applied when reflect ends — 202, state "recorded".
     * Retrying it every few minutes would flood the reflect queue.
     */
    public function testReflectDeferralIsAcknowledged(): void
    {
        self::assertNull(Acknowledgement::purgeFailure(202, '{"status":"deferred","queued_purges":3,"state":"recorded","barrier":"deferred"}'));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function notReflectAcks(): array
    {
        return [
            'reflect queue full: 503 refused' => [503, '{"status":"refused","queued_purges":10000,"state":"refused","barrier":"none"}'],
            '202 without state' => [202, '{"status":"deferred"}'],
            '202 refused' => [202, '{"state":"refused"}'],
            '202 not JSON' => [202, 'accepted'],
            '200 recorded but no purge schema' => [200, '{"state":"recorded"}'],
        ];
    }

    #[DataProvider('notReflectAcks')]
    public function testOtherDeferralsAreNotAcknowledged(int $status, string $body): void
    {
        self::assertNotNull(Acknowledgement::purgeFailure($status, $body));
    }

    public function testClearAcknowledgement(): void
    {
        self::assertNull(Acknowledgement::clearFailure(200, '{"cleared":true,"entries_removed":3,"bytes_freed":10}'));
        self::assertSame('HTTP 200 — not an acknowledgement', Acknowledgement::clearFailure(200, '{"cleared":false}'));
        self::assertSame('HTTP 401 — no', Acknowledgement::clearFailure(401, '{"error":"no"}'));
    }

    public function testLongErrorIsClippedToOneLine(): void
    {
        $reason = (string) Acknowledgement::purgeFailure(500, (string) json_encode(['error' => str_repeat("x\n", 300)]));
        self::assertStringNotContainsString("\n", $reason);
        self::assertLessThanOrEqual(220, strlen($reason));
    }
}
