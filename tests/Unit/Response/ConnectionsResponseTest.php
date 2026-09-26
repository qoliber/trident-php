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
use Qoliber\Trident\Response\BackendConnections;
use Qoliber\Trident\Response\ConnectionsResponse;

class ConnectionsResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'total_active' => 50,
            'total_idle' => 100,
            'backends' => [
                [
                    'name' => 'api-backend',
                    'active' => 30,
                    'idle' => 70,
                    'max_connections' => 200,
                    'waiting_requests' => 5,
                ],
                [
                    'name' => 'static-backend',
                    'active' => 20,
                    'idle' => 30,
                    'max_connections' => 100,
                    'waiting_requests' => 0,
                ],
            ],
        ];

        $response = ConnectionsResponse::fromArray($data);

        $this->assertEquals(50, $response->totalActive);
        $this->assertEquals(100, $response->totalIdle);
        $this->assertCount(2, $response->backends);
    }

    public function testBackendConnections(): void
    {
        $data = [
            'name' => 'test-backend',
            'active' => 25,
            'idle' => 75,
            'max_connections' => 200,
            'waiting_requests' => 10,
        ];

        $backend = BackendConnections::fromArray($data);

        $this->assertEquals('test-backend', $backend->name);
        $this->assertEquals(25, $backend->active);
        $this->assertEquals(75, $backend->idle);
        $this->assertEquals(200, $backend->maxConnections);
        $this->assertEquals(10, $backend->waitingRequests);
    }

    public function testBackendConnectionsAlternativeFieldNames(): void
    {
        $backend = BackendConnections::fromArray([
            'name' => 'alt-backend',
            'active' => 10,
            'idle' => 20,
            'max' => 50,
            'waiting' => 3,
        ]);

        $this->assertEquals(50, $backend->maxConnections);
        $this->assertEquals(3, $backend->waitingRequests);
    }

    public function testGetTotalConnections(): void
    {
        $response = ConnectionsResponse::fromArray([
            'total_active' => 30,
            'total_idle' => 70,
        ]);

        $this->assertEquals(100, $response->getTotalConnections());
    }

    public function testBackendGetTotalConnections(): void
    {
        $backend = BackendConnections::fromArray([
            'name' => 'test',
            'active' => 15,
            'idle' => 35,
        ]);

        $this->assertEquals(50, $backend->getTotalConnections());
    }

    public function testBackendUtilizationPercent(): void
    {
        $backend = BackendConnections::fromArray([
            'name' => 'test',
            'active' => 50,
            'idle' => 50,
            'max_connections' => 200,
        ]);

        $this->assertEquals(25.0, $backend->getUtilizationPercent());
    }

    public function testBackendUtilizationPercentZeroMax(): void
    {
        $backend = BackendConnections::fromArray([
            'name' => 'test',
            'active' => 10,
            'max_connections' => 0,
        ]);

        $this->assertEquals(0.0, $backend->getUtilizationPercent());
    }

    public function testBackendHasWaitingRequests(): void
    {
        $withWaiting = BackendConnections::fromArray([
            'name' => 'busy',
            'waiting_requests' => 5,
        ]);
        $this->assertTrue($withWaiting->hasWaitingRequests());

        $noWaiting = BackendConnections::fromArray([
            'name' => 'idle',
            'waiting_requests' => 0,
        ]);
        $this->assertFalse($noWaiting->hasWaitingRequests());
    }

    public function testGetters(): void
    {
        $response = ConnectionsResponse::fromArray([
            'total_active' => 25,
            'total_idle' => 75,
            'backends' => [['name' => 'test']],
        ]);

        $this->assertEquals(25, $response->getTotalActive());
        $this->assertEquals(75, $response->getTotalIdle());
        $this->assertCount(1, $response->getBackends());
    }

    public function testToArray(): void
    {
        $response = ConnectionsResponse::fromArray([
            'total_active' => 10,
            'total_idle' => 20,
            'backends' => [
                [
                    'name' => 'backend1',
                    'active' => 5,
                    'idle' => 10,
                    'max_connections' => 50,
                    'waiting_requests' => 0,
                ],
            ],
        ]);

        $output = $response->toArray();

        $this->assertEquals(10, $output['total_active']);
        $this->assertEquals(20, $output['total_idle']);
        $this->assertCount(1, $output['backends']);
    }

    public function testBackendToArray(): void
    {
        $backend = BackendConnections::fromArray([
            'name' => 'test-backend',
            'active' => 10,
            'idle' => 20,
            'max_connections' => 100,
            'waiting_requests' => 2,
        ]);

        $output = $backend->toArray();

        $this->assertEquals('test-backend', $output['name']);
        $this->assertEquals(10, $output['active']);
        $this->assertEquals(20, $output['idle']);
        $this->assertEquals(100, $output['max_connections']);
        $this->assertEquals(2, $output['waiting_requests']);
    }

    public function testDefaultValues(): void
    {
        $response = ConnectionsResponse::fromArray([]);

        $this->assertEquals(0, $response->totalActive);
        $this->assertEquals(0, $response->totalIdle);
        $this->assertEquals([], $response->backends);
    }
}
