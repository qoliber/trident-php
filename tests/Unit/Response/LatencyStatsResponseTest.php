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
use Qoliber\Trident\Response\LatencyStatsResponse;

class LatencyStatsResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'latency' => [
                'avg_ms' => 45.5,
                'p50_ms' => 42.0,
                'p75_ms' => 55.0,
                'p90_ms' => 75.0,
                'p95_ms' => 100.0,
                'p99_ms' => 150.0,
                'min_ms' => 5.0,
                'max_ms' => 500.0,
            ],
            'sample_count' => 10000,
            'window_secs' => 300,
        ];

        $response = LatencyStatsResponse::fromArray($data);

        $this->assertEquals(45.5, $response->getAvgMs());
        $this->assertEquals(42.0, $response->getP50Ms());
        $this->assertEquals(55.0, $response->getP75Ms());
        $this->assertEquals(75.0, $response->getP90Ms());
        $this->assertEquals(100.0, $response->getP95Ms());
        $this->assertEquals(150.0, $response->getP99Ms());
        $this->assertEquals(5.0, $response->getMinMs());
        $this->assertEquals(500.0, $response->getMaxMs());
        $this->assertEquals(10000, $response->sampleCount);
        $this->assertEquals(300, $response->windowSecs);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = LatencyStatsResponse::fromArray([]);

        $this->assertNull($response->latency);
        $this->assertEquals(0, $response->sampleCount);
        $this->assertEquals(0, $response->windowSecs);
        $this->assertEquals(0.0, $response->getAvgMs());
        $this->assertEquals(0.0, $response->getP50Ms());
        $this->assertEquals(0.0, $response->getP95Ms());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $latency = [
            'avg_ms' => 50.0,
            'p50_ms' => 45.0,
            'p95_ms' => 90.0,
            'p99_ms' => 120.0,
        ];

        $response = new LatencyStatsResponse(
            latency: $latency,
            sampleCount: 5000,
            windowSecs: 60
        );

        $this->assertEquals($latency, $response->latency);
        $this->assertEquals(5000, $response->sampleCount);
        $this->assertEquals(60, $response->windowSecs);
    }
}
