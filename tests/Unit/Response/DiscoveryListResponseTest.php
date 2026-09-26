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
use Qoliber\Trident\Response\DiscoveryBackend;
use Qoliber\Trident\Response\DiscoveryListResponse;

class DiscoveryListResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'backends' => [
                [
                    'name' => 'api-backend',
                    'hostname' => 'api.example.com',
                    'resolved_ips' => ['192.168.1.1', '192.168.1.2'],
                    'healthy_count' => 2,
                    'unhealthy_count' => 0,
                    'last_resolved' => '2024-01-15T10:30:00Z',
                ],
                [
                    'name' => 'static-backend',
                    'hostname' => 'static.example.com',
                    'resolved_ips' => ['10.0.0.1'],
                    'healthy_count' => 1,
                    'unhealthy_count' => 0,
                ],
            ],
            'total' => 2,
        ];

        $response = DiscoveryListResponse::fromArray($data);

        $this->assertCount(2, $response->backends);
        $this->assertEquals(2, $response->total);
    }

    public function testDiscoveryBackend(): void
    {
        $data = [
            'name' => 'test-backend',
            'hostname' => 'test.example.com',
            'resolved_ips' => ['1.2.3.4', '5.6.7.8', '9.10.11.12'],
            'healthy_count' => 2,
            'unhealthy_count' => 1,
            'last_resolved' => '2024-01-15T12:00:00Z',
        ];

        $backend = DiscoveryBackend::fromArray($data);

        $this->assertEquals('test-backend', $backend->name);
        $this->assertEquals('test.example.com', $backend->hostname);
        $this->assertCount(3, $backend->resolvedIps);
        $this->assertEquals(2, $backend->healthyCount);
        $this->assertEquals(1, $backend->unhealthyCount);
        $this->assertEquals('2024-01-15T12:00:00Z', $backend->lastResolved);
    }

    public function testDiscoveryBackendAlternativeFieldNames(): void
    {
        $backend = DiscoveryBackend::fromArray([
            'name' => 'alt-backend',
            'host' => 'alt.example.com',
            'ips' => ['1.1.1.1'],
            'healthy' => 1,
            'unhealthy' => 0,
        ]);

        $this->assertEquals('alt.example.com', $backend->hostname);
        $this->assertEquals(['1.1.1.1'], $backend->resolvedIps);
        $this->assertEquals(1, $backend->healthyCount);
        $this->assertEquals(0, $backend->unhealthyCount);
    }

    public function testGetTotalIps(): void
    {
        $backend = DiscoveryBackend::fromArray([
            'name' => 'test',
            'resolved_ips' => ['1.1.1.1', '2.2.2.2', '3.3.3.3'],
        ]);

        $this->assertEquals(3, $backend->getTotalIps());
    }

    public function testIsFullyHealthy(): void
    {
        $fullyHealthy = DiscoveryBackend::fromArray([
            'name' => 'healthy',
            'healthy_count' => 3,
            'unhealthy_count' => 0,
        ]);
        $this->assertTrue($fullyHealthy->isFullyHealthy());

        $partiallyHealthy = DiscoveryBackend::fromArray([
            'name' => 'partial',
            'healthy_count' => 2,
            'unhealthy_count' => 1,
        ]);
        $this->assertFalse($partiallyHealthy->isFullyHealthy());

        $noHealthy = DiscoveryBackend::fromArray([
            'name' => 'none',
            'healthy_count' => 0,
            'unhealthy_count' => 0,
        ]);
        $this->assertFalse($noHealthy->isFullyHealthy());
    }

    public function testIsEmpty(): void
    {
        $empty = DiscoveryListResponse::fromArray([
            'backends' => [],
            'total' => 0,
        ]);
        $this->assertTrue($empty->isEmpty());

        $notEmpty = DiscoveryListResponse::fromArray([
            'backends' => [['name' => 'test']],
            'total' => 1,
        ]);
        $this->assertFalse($notEmpty->isEmpty());
    }

    public function testGetters(): void
    {
        $response = DiscoveryListResponse::fromArray([
            'backends' => [['name' => 'test']],
            'total' => 5,
        ]);

        $this->assertCount(1, $response->getBackends());
        $this->assertEquals(5, $response->getTotal());
    }

    public function testToArray(): void
    {
        $response = DiscoveryListResponse::fromArray([
            'backends' => [
                [
                    'name' => 'backend1',
                    'hostname' => 'host1.example.com',
                    'resolved_ips' => ['1.1.1.1'],
                    'healthy_count' => 1,
                    'unhealthy_count' => 0,
                    'last_resolved' => '2024-01-15T10:00:00Z',
                ],
            ],
            'total' => 1,
        ]);

        $output = $response->toArray();

        $this->assertArrayHasKey('backends', $output);
        $this->assertArrayHasKey('total', $output);
        $this->assertCount(1, $output['backends']);
        $this->assertEquals('backend1', $output['backends'][0]['name']);
    }

    public function testBackendToArray(): void
    {
        $backend = DiscoveryBackend::fromArray([
            'name' => 'test-backend',
            'hostname' => 'test.example.com',
            'resolved_ips' => ['1.2.3.4'],
            'healthy_count' => 1,
            'unhealthy_count' => 0,
            'last_resolved' => '2024-01-15T10:00:00Z',
        ]);

        $output = $backend->toArray();

        $this->assertEquals('test-backend', $output['name']);
        $this->assertEquals('test.example.com', $output['hostname']);
        $this->assertEquals(['1.2.3.4'], $output['resolved_ips']);
        $this->assertEquals(1, $output['healthy_count']);
        $this->assertEquals(0, $output['unhealthy_count']);
    }

    public function testDefaultValues(): void
    {
        $response = DiscoveryListResponse::fromArray([]);

        $this->assertEquals([], $response->backends);
        $this->assertEquals(0, $response->total);
    }
}
