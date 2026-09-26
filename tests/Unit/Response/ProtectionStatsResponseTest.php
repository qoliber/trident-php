<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Response\BackendProtectionStats;
use Qoliber\Trident\Response\ProtectionStatsResponse;

class ProtectionStatsResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'enabled' => true,
            'total_tripped' => 5,
            'active_trips' => 2,
            'backends' => [
                [
                    'name' => 'backend1',
                    'tripped' => true,
                    'trip_count' => 3,
                    'error_count' => 150,
                    'error_rate' => 0.15,
                    'last_trip' => '2024-01-15T10:30:00Z',
                    'cooldown_remaining' => 30,
                ],
                [
                    'name' => 'backend2',
                    'tripped' => false,
                    'trip_count' => 2,
                    'error_count' => 50,
                    'error_rate' => 0.05,
                ],
            ],
        ];

        $response = ProtectionStatsResponse::fromArray($data);

        $this->assertTrue($response->enabled);
        $this->assertEquals(5, $response->totalTripped);
        $this->assertEquals(2, $response->activeTrips);
        $this->assertCount(2, $response->backends);
    }

    public function testBackendProtectionStats(): void
    {
        $data = [
            'name' => 'api-backend',
            'tripped' => true,
            'trip_count' => 10,
            'error_count' => 500,
            'error_rate' => 0.25,
            'last_trip' => '2024-01-15T12:00:00Z',
            'cooldown_remaining' => 60,
        ];

        $stats = BackendProtectionStats::fromArray($data);

        $this->assertEquals('api-backend', $stats->name);
        $this->assertTrue($stats->tripped);
        $this->assertTrue($stats->isTripped());
        $this->assertEquals(10, $stats->tripCount);
        $this->assertEquals(500, $stats->errorCount);
        $this->assertEquals(0.25, $stats->errorRate);
        $this->assertEquals('2024-01-15T12:00:00Z', $stats->lastTrip);
        $this->assertEquals(60, $stats->cooldownRemaining);
    }

    public function testBackendProtectionStatsNotTripped(): void
    {
        $stats = BackendProtectionStats::fromArray([
            'name' => 'healthy-backend',
            'tripped' => false,
        ]);

        $this->assertFalse($stats->isTripped());
    }

    public function testIsEnabled(): void
    {
        $enabled = ProtectionStatsResponse::fromArray(['enabled' => true]);
        $this->assertTrue($enabled->isEnabled());

        $disabled = ProtectionStatsResponse::fromArray(['enabled' => false]);
        $this->assertFalse($disabled->isEnabled());
    }

    public function testHasActiveTrips(): void
    {
        $withTrips = ProtectionStatsResponse::fromArray([
            'active_trips' => 3,
        ]);
        $this->assertTrue($withTrips->hasActiveTrips());

        $noTrips = ProtectionStatsResponse::fromArray([
            'active_trips' => 0,
        ]);
        $this->assertFalse($noTrips->hasActiveTrips());
    }

    public function testGetters(): void
    {
        $response = ProtectionStatsResponse::fromArray([
            'enabled' => true,
            'total_tripped' => 10,
            'active_trips' => 2,
            'backends' => [['name' => 'test']],
        ]);

        $this->assertEquals(10, $response->getTotalTripped());
        $this->assertEquals(2, $response->getActiveTrips());
        $this->assertCount(1, $response->getBackends());
    }

    public function testToArray(): void
    {
        $response = ProtectionStatsResponse::fromArray([
            'enabled' => true,
            'total_tripped' => 5,
            'active_trips' => 1,
            'backends' => [
                [
                    'name' => 'backend1',
                    'tripped' => false,
                    'trip_count' => 2,
                    'error_count' => 100,
                    'error_rate' => 0.1,
                ],
            ],
        ]);

        $output = $response->toArray();

        $this->assertTrue($output['enabled']);
        $this->assertEquals(5, $output['total_tripped']);
        $this->assertCount(1, $output['backends']);
    }

    public function testBackendProtectionStatsToArray(): void
    {
        $stats = BackendProtectionStats::fromArray([
            'name' => 'test-backend',
            'tripped' => true,
            'trip_count' => 5,
            'error_count' => 200,
            'error_rate' => 0.2,
            'last_trip' => '2024-01-15T10:00:00Z',
            'cooldown_remaining' => 45,
        ]);

        $output = $stats->toArray();

        $this->assertEquals('test-backend', $output['name']);
        $this->assertTrue($output['tripped']);
        $this->assertEquals(5, $output['trip_count']);
    }

    public function testDefaultValues(): void
    {
        $response = ProtectionStatsResponse::fromArray([]);

        $this->assertFalse($response->enabled);
        $this->assertEquals(0, $response->totalTripped);
        $this->assertEquals(0, $response->activeTrips);
        $this->assertEquals([], $response->backends);
    }
}
