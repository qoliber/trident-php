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
use Qoliber\Trident\Response\BackendsResponse;

class BackendsResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'total' => 3,
            'healthy' => 2,
            'unhealthy' => 1,
            'backends' => [
                [
                    'name' => 'origin',
                    'host' => 'backend.internal',
                    'port' => 8080,
                    'status' => 'healthy',
                    'healthy' => true,
                    'total_requests' => 10000,
                    'total_errors' => 5,
                    'active_connections' => 50,
                    'avg_response_ms' => 25.5,
                ],
                [
                    'name' => 'api',
                    'host' => 'api.internal',
                    'port' => 8081,
                    'status' => 'healthy',
                    'healthy' => true,
                    'total_requests' => 5000,
                    'total_errors' => 2,
                    'active_connections' => 25,
                    'avg_response_ms' => 15.0,
                ],
                [
                    'name' => 'legacy',
                    'host' => 'legacy.internal',
                    'port' => 8082,
                    'status' => 'unhealthy',
                    'healthy' => false,
                    'total_requests' => 1000,
                    'total_errors' => 100,
                    'active_connections' => 0,
                    'avg_response_ms' => 0.0,
                ],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertEquals(3, $response->total);
        $this->assertEquals(2, $response->healthy);
        $this->assertEquals(1, $response->unhealthy);
        $this->assertCount(3, $response->backends);
        $this->assertEquals('origin', $response->backends[0]['name']);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = BackendsResponse::fromArray([]);

        $this->assertEquals(0, $response->total);
        $this->assertEquals(0, $response->healthy);
        $this->assertEquals(0, $response->unhealthy);
        $this->assertEmpty($response->backends);
    }

    public function testIsAllHealthyWithAllHealthy(): void
    {
        $data = [
            'total' => 2,
            'healthy' => 2,
            'unhealthy' => 0,
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
                ['name' => 'api', 'healthy' => true],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertTrue($response->isAllHealthy());
    }

    public function testIsAllHealthyWithSomeUnhealthy(): void
    {
        $data = [
            'total' => 2,
            'healthy' => 1,
            'unhealthy' => 1,
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
                ['name' => 'api', 'healthy' => false],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertFalse($response->isAllHealthy());
    }

    public function testIsAllHealthyWithNoBackends(): void
    {
        $data = [
            'total' => 0,
            'healthy' => 0,
            'unhealthy' => 0,
            'backends' => [],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertFalse($response->isAllHealthy());
    }

    public function testGetBackendExisting(): void
    {
        $data = [
            'total' => 2,
            'healthy' => 2,
            'unhealthy' => 0,
            'backends' => [
                ['name' => 'origin', 'host' => 'backend.internal', 'healthy' => true],
                ['name' => 'api', 'host' => 'api.internal', 'healthy' => true],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $backend = $response->getBackend('origin');
        $this->assertIsArray($backend);
        $this->assertEquals('origin', $backend['name']);
        $this->assertEquals('backend.internal', $backend['host']);
    }

    public function testGetBackendNonExisting(): void
    {
        $data = [
            'total' => 1,
            'healthy' => 1,
            'unhealthy' => 0,
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertNull($response->getBackend('nonexistent'));
    }

    public function testIsBackendHealthyWithHealthyBackend(): void
    {
        $data = [
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertTrue($response->isBackendHealthy('origin'));
    }

    public function testIsBackendHealthyWithUnhealthyBackend(): void
    {
        $data = [
            'backends' => [
                ['name' => 'origin', 'healthy' => false],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertFalse($response->isBackendHealthy('origin'));
    }

    public function testIsBackendHealthyWithNonExistingBackend(): void
    {
        $data = [
            'backends' => [
                ['name' => 'origin', 'healthy' => true],
            ],
        ];

        $response = BackendsResponse::fromArray($data);

        $this->assertFalse($response->isBackendHealthy('nonexistent'));
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $backends = [['name' => 'test', 'healthy' => true]];

        $response = new BackendsResponse(
            total: 1,
            healthy: 1,
            unhealthy: 0,
            backends: $backends
        );

        $this->assertEquals(1, $response->total);
        $this->assertEquals(1, $response->healthy);
        $this->assertEquals(0, $response->unhealthy);
        $this->assertEquals($backends, $response->backends);
    }
}
