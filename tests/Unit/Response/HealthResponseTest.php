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
use Qoliber\Trident\Response\HealthResponse;

class HealthResponseTest extends TestCase
{
    public function testFromArrayWithHealthyStatus(): void
    {
        $data = [
            'status' => 'healthy',
            'version' => '1.0.0',
            'uptime' => 3600,
        ];

        $response = HealthResponse::fromArray($data);

        $this->assertTrue($response->isHealthy());
        $this->assertEquals('healthy', $response->status);
        $this->assertEquals('1.0.0', $response->version);
        $this->assertEquals(3600, $response->uptime);
    }

    public function testFromArrayWithHealthyBoolean(): void
    {
        $data = [
            'healthy' => true,
            'status' => 'ok',
        ];

        $response = HealthResponse::fromArray($data);

        $this->assertTrue($response->isHealthy());
        $this->assertEquals('ok', $response->status);
    }

    public function testFromArrayWithUnhealthyStatus(): void
    {
        $data = [
            'status' => 'unhealthy',
            'healthy' => false,
        ];

        $response = HealthResponse::fromArray($data);

        $this->assertFalse($response->isHealthy());
        $this->assertEquals('unhealthy', $response->status);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = HealthResponse::fromArray([]);

        $this->assertFalse($response->isHealthy());
        $this->assertEquals('unknown', $response->status);
        $this->assertNull($response->version);
        $this->assertNull($response->uptime);
    }

    public function testFromArrayWithBackends(): void
    {
        $data = [
            'status' => 'healthy',
            'backends' => [
                'origin' => ['healthy' => true],
                'api' => ['healthy' => false],
            ],
        ];

        $response = HealthResponse::fromArray($data);

        $this->assertTrue($response->isHealthy());
        $this->assertIsArray($response->backends);
        $this->assertArrayHasKey('origin', $response->backends);
        $this->assertArrayHasKey('api', $response->backends);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new HealthResponse(
            healthy: true,
            status: 'healthy',
            version: '2.0.0',
            uptime: 7200,
            backends: ['default' => ['healthy' => true]]
        );

        $this->assertTrue($response->healthy);
        $this->assertEquals('healthy', $response->status);
        $this->assertEquals('2.0.0', $response->version);
        $this->assertEquals(7200, $response->uptime);
        $this->assertIsArray($response->backends);
    }
}
