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
use Qoliber\Trident\Response\BackendDetailResponse;

class BackendDetailResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'name' => 'origin',
            'healthy' => true,
            'host' => 'localhost',
            'port' => 3000,
            'address' => '127.0.0.1:3000',
            'status' => 'healthy',
            'weight' => 100,
            'max_connections' => 1000,
            'tls' => false,
            'stats' => [
                'total_requests' => 50000,
                'failed_requests' => 10,
                'error_rate' => 0.0002,
                'active_connections' => 5,
            ],
            'latency' => [
                'avg_ms' => 45.5,
                'p50_ms' => 30.0,
                'p95_ms' => 100.0,
                'p99_ms' => 150.0,
            ],
        ];

        $response = BackendDetailResponse::fromArray($data);

        $this->assertEquals('origin', $response->name);
        $this->assertTrue($response->isHealthy());
        $this->assertEquals('localhost', $response->host);
        $this->assertEquals(3000, $response->port);
        $this->assertEquals('127.0.0.1:3000', $response->address);
        $this->assertEquals('healthy', $response->status);
        $this->assertEquals(100, $response->weight);
        $this->assertEquals(1000, $response->maxConnections);
        $this->assertFalse($response->tls);
        $this->assertEquals(50000, $response->getRequestCount());
        $this->assertEquals(10, $response->getErrorCount());
        $this->assertEquals(0.0002, $response->getErrorRate());
        $this->assertEquals(5, $response->getActiveConnections());
        $this->assertEquals(45.5, $response->getAvgLatencyMs());
        $this->assertEquals(30.0, $response->getP50LatencyMs());
        $this->assertEquals(100.0, $response->getP95LatencyMs());
        $this->assertEquals(150.0, $response->getP99LatencyMs());
        $this->assertEquals('http://localhost:3000', $response->getUrl());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = BackendDetailResponse::fromArray([]);

        $this->assertEquals('', $response->name);
        $this->assertFalse($response->isHealthy());
        $this->assertEquals('', $response->host);
        $this->assertEquals(80, $response->port);
        $this->assertNull($response->address);
        $this->assertNull($response->status);
        $this->assertEquals(0, $response->getRequestCount());
        $this->assertEquals(0, $response->getErrorCount());
        $this->assertEquals(0.0, $response->getAvgLatencyMs());
    }

    public function testFromArrayWithUnhealthyBackend(): void
    {
        $data = [
            'name' => 'backup',
            'healthy' => false,
            'host' => 'localhost',
            'port' => 4000,
            'status' => 'unhealthy',
        ];

        $response = BackendDetailResponse::fromArray($data);

        $this->assertFalse($response->isHealthy());
        $this->assertEquals('unhealthy', $response->status);
    }

    public function testGetUrlWithTls(): void
    {
        $data = [
            'name' => 'secure',
            'healthy' => true,
            'host' => 'secure.example.com',
            'port' => 443,
            'tls' => true,
        ];

        $response = BackendDetailResponse::fromArray($data);

        $this->assertEquals('https://secure.example.com:443', $response->getUrl());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $stats = ['total_requests' => 1000, 'failed_requests' => 5];
        $latency = ['avg_ms' => 30.0, 'p50_ms' => 25.0];

        $response = new BackendDetailResponse(
            name: 'test-backend',
            healthy: true,
            host: 'test',
            port: 8080,
            address: '127.0.0.1:8080',
            status: 'healthy',
            weight: 50,
            maxConnections: 500,
            tls: false,
            stats: $stats,
            latency: $latency
        );

        $this->assertEquals('test-backend', $response->name);
        $this->assertTrue($response->healthy);
        $this->assertEquals('test', $response->host);
        $this->assertEquals(8080, $response->port);
        $this->assertEquals($stats, $response->stats);
        $this->assertEquals($latency, $response->latency);
    }
}
