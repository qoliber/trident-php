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
use Qoliber\Trident\Response\DiscoveryDetailResponse;
use Qoliber\Trident\Response\DiscoveryIpStatus;

class DiscoveryDetailResponseTest extends TestCase
{
    public function testFromArrayBasic(): void
    {
        $data = [
            'name' => 'api-backend',
            'hostname' => 'api.example.com',
            'port' => 8080,
            'ips' => [
                [
                    'ip' => '192.168.1.1',
                    'healthy' => true,
                    'requests' => 1000,
                    'errors' => 5,
                    'avg_response_ms' => 25.5,
                ],
                [
                    'ip' => '192.168.1.2',
                    'healthy' => false,
                    'requests' => 500,
                    'errors' => 50,
                    'avg_response_ms' => 150.0,
                    'last_error' => 'Connection timeout',
                ],
            ],
            'refresh_interval_secs' => 60,
            'last_resolved' => '2024-01-15T10:30:00Z',
            'next_refresh' => '2024-01-15T10:31:00Z',
        ];

        $response = DiscoveryDetailResponse::fromArray($data);

        $this->assertEquals('api-backend', $response->name);
        $this->assertEquals('api.example.com', $response->hostname);
        $this->assertEquals(8080, $response->port);
        $this->assertCount(2, $response->ips);
        $this->assertEquals(60, $response->refreshIntervalSecs);
        $this->assertEquals('2024-01-15T10:30:00Z', $response->lastResolved);
        $this->assertEquals('2024-01-15T10:31:00Z', $response->nextRefresh);
    }

    public function testDiscoveryIpStatus(): void
    {
        $data = [
            'ip' => '10.0.0.1',
            'healthy' => true,
            'requests' => 5000,
            'errors' => 25,
            'avg_response_ms' => 15.5,
        ];

        $ipStatus = DiscoveryIpStatus::fromArray($data);

        $this->assertEquals('10.0.0.1', $ipStatus->ip);
        $this->assertTrue($ipStatus->healthy);
        $this->assertTrue($ipStatus->isHealthy());
        $this->assertEquals(5000, $ipStatus->requests);
        $this->assertEquals(25, $ipStatus->errors);
        $this->assertEquals(15.5, $ipStatus->avgResponseMs);
        $this->assertNull($ipStatus->lastError);
    }

    public function testDiscoveryIpStatusAlternativeFieldNames(): void
    {
        $ipStatus = DiscoveryIpStatus::fromArray([
            'address' => '172.16.0.1',
            'healthy' => true,
        ]);

        $this->assertEquals('172.16.0.1', $ipStatus->ip);
    }

    public function testGetErrorRate(): void
    {
        $noErrors = DiscoveryIpStatus::fromArray([
            'ip' => '1.1.1.1',
            'requests' => 100,
            'errors' => 0,
        ]);
        $this->assertEquals(0.0, $noErrors->getErrorRate());

        $someErrors = DiscoveryIpStatus::fromArray([
            'ip' => '2.2.2.2',
            'requests' => 1000,
            'errors' => 50,
        ]);
        $this->assertEquals(5.0, $someErrors->getErrorRate());

        $noRequests = DiscoveryIpStatus::fromArray([
            'ip' => '3.3.3.3',
            'requests' => 0,
            'errors' => 0,
        ]);
        $this->assertEquals(0.0, $noRequests->getErrorRate());
    }

    public function testGetHealthyIps(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'test',
            'ips' => [
                ['ip' => '1.1.1.1', 'healthy' => true, 'requests' => 100, 'errors' => 0],
                ['ip' => '2.2.2.2', 'healthy' => false, 'requests' => 50, 'errors' => 10],
                ['ip' => '3.3.3.3', 'healthy' => true, 'requests' => 200, 'errors' => 1],
            ],
        ]);

        $healthy = $response->getHealthyIps();
        $this->assertCount(2, $healthy);
    }

    public function testGetUnhealthyIps(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'test',
            'ips' => [
                ['ip' => '1.1.1.1', 'healthy' => true, 'requests' => 100, 'errors' => 0],
                ['ip' => '2.2.2.2', 'healthy' => false, 'requests' => 50, 'errors' => 10],
                ['ip' => '3.3.3.3', 'healthy' => false, 'requests' => 30, 'errors' => 20],
            ],
        ]);

        $unhealthy = $response->getUnhealthyIps();
        $this->assertCount(2, $unhealthy);
    }

    public function testGetHealthyUnhealthyCount(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'test',
            'ips' => [
                ['ip' => '1.1.1.1', 'healthy' => true],
                ['ip' => '2.2.2.2', 'healthy' => true],
                ['ip' => '3.3.3.3', 'healthy' => false],
            ],
        ]);

        $this->assertEquals(2, $response->getHealthyCount());
        $this->assertEquals(1, $response->getUnhealthyCount());
    }

    public function testFromArrayAlternativeFieldNames(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'test',
            'host' => 'test.example.com',
            'refresh_interval' => 30,
        ]);

        $this->assertEquals('test.example.com', $response->hostname);
        $this->assertEquals(30, $response->refreshIntervalSecs);
    }

    public function testGetters(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'test',
            'ips' => [
                ['ip' => '1.1.1.1', 'healthy' => true],
            ],
        ]);

        $this->assertCount(1, $response->getIps());
    }

    public function testToArray(): void
    {
        $response = DiscoveryDetailResponse::fromArray([
            'name' => 'backend1',
            'hostname' => 'backend.example.com',
            'port' => 443,
            'ips' => [
                ['ip' => '1.1.1.1', 'healthy' => true, 'requests' => 100, 'errors' => 0],
            ],
            'refresh_interval_secs' => 60,
            'last_resolved' => '2024-01-15T10:00:00Z',
            'next_refresh' => '2024-01-15T10:01:00Z',
        ]);

        $output = $response->toArray();

        $this->assertEquals('backend1', $output['name']);
        $this->assertEquals('backend.example.com', $output['hostname']);
        $this->assertEquals(443, $output['port']);
        $this->assertCount(1, $output['ips']);
    }

    public function testIpStatusToArray(): void
    {
        $ipStatus = DiscoveryIpStatus::fromArray([
            'ip' => '10.0.0.1',
            'healthy' => true,
            'requests' => 1000,
            'errors' => 10,
            'avg_response_ms' => 25.0,
            'last_error' => null,
        ]);

        $output = $ipStatus->toArray();

        $this->assertEquals('10.0.0.1', $output['ip']);
        $this->assertTrue($output['healthy']);
        $this->assertEquals(1000, $output['requests']);
        $this->assertEquals(10, $output['errors']);
        $this->assertEquals(25.0, $output['avg_response_ms']);
    }

    public function testDefaultValues(): void
    {
        $response = DiscoveryDetailResponse::fromArray(['name' => 'test']);

        $this->assertEquals('test', $response->name);
        $this->assertEquals('', $response->hostname);
        $this->assertEquals(80, $response->port);
        $this->assertEquals([], $response->ips);
        $this->assertEquals(60, $response->refreshIntervalSecs);
        $this->assertNull($response->lastResolved);
        $this->assertNull($response->nextRefresh);
    }
}
