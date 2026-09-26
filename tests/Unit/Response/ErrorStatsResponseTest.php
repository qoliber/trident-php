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
use Qoliber\Trident\Response\ErrorStatsResponse;

class ErrorStatsResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'total_requests' => 10000,
            'total_errors' => 50,
            'error_rate' => 0.005,
            'by_status' => [
                500 => 30,
                502 => 15,
                503 => 5,
            ],
            'recent' => [
                ['url' => '/api/test', 'status' => 500, 'time' => '2024-01-15T10:00:00Z'],
                ['url' => '/api/fail', 'status' => 502, 'time' => '2024-01-15T09:55:00Z'],
            ],
        ];

        $response = ErrorStatsResponse::fromArray($data);

        $this->assertEquals(10000, $response->totalRequests);
        $this->assertEquals(50, $response->totalErrors);
        $this->assertEquals(0.005, $response->errorRate);
        $this->assertTrue($response->hasErrors());
        $this->assertEquals(0.5, $response->getErrorPercentage());
        $this->assertEquals(30, $response->getCountForStatus(500));
        $this->assertEquals(15, $response->getCountForStatus(502));
        $this->assertEquals(5, $response->getCountForStatus(503));
        $this->assertEquals(0, $response->getCountForStatus(404));
        $this->assertCount(2, $response->recent);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = ErrorStatsResponse::fromArray([]);

        $this->assertEquals(0, $response->totalRequests);
        $this->assertEquals(0, $response->totalErrors);
        $this->assertEquals(0.0, $response->errorRate);
        $this->assertFalse($response->hasErrors());
        $this->assertEmpty($response->byStatus);
        $this->assertEmpty($response->recent);
    }

    public function testFromArrayWithNoErrors(): void
    {
        $data = [
            'total_requests' => 5000,
            'total_errors' => 0,
            'error_rate' => 0.0,
            'by_status' => [],
            'recent' => [],
        ];

        $response = ErrorStatsResponse::fromArray($data);

        $this->assertFalse($response->hasErrors());
        $this->assertEquals(0.0, $response->getErrorPercentage());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $byStatus = [500 => 10, 502 => 5];
        $recent = [['url' => '/test', 'status' => 500, 'time' => '2024-01-01T00:00:00Z']];

        $response = new ErrorStatsResponse(
            totalRequests: 1000,
            totalErrors: 15,
            errorRate: 0.015,
            byStatus: $byStatus,
            recent: $recent
        );

        $this->assertEquals(1000, $response->totalRequests);
        $this->assertEquals(15, $response->totalErrors);
        $this->assertEquals(0.015, $response->errorRate);
        $this->assertEquals($byStatus, $response->byStatus);
        $this->assertEquals($recent, $response->recent);
    }
}
