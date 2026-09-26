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
use Qoliber\Trident\Response\RefreshQueueResponse;

class RefreshQueueResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'pending' => 5,
            'queue_capacity' => 1000,
            'workers' => 4,
            'stats' => [
                'queued' => 100,
                'completed' => 95,
                'failed' => 3,
                'duplicates' => 2,
                'queue_full' => 0,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(5, $response->pending);
        $this->assertEquals(1000, $response->queueCapacity);
        $this->assertEquals(4, $response->workers);
        $this->assertEquals(100, $response->queued);
        $this->assertEquals(95, $response->completed);
        $this->assertEquals(3, $response->failed);
        $this->assertEquals(2, $response->duplicates);
        $this->assertEquals(0, $response->queueFull);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = RefreshQueueResponse::fromArray([]);

        $this->assertEquals(0, $response->pending);
        $this->assertEquals(0, $response->queueCapacity);
        $this->assertEquals(0, $response->workers);
        $this->assertEquals(0, $response->queued);
        $this->assertEquals(0, $response->completed);
        $this->assertEquals(0, $response->failed);
        $this->assertEquals(0, $response->duplicates);
        $this->assertEquals(0, $response->queueFull);
    }

    public function testGetSuccessRateWithCompletedAndFailed(): void
    {
        $data = [
            'stats' => [
                'completed' => 90,
                'failed' => 10,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(90.0, $response->getSuccessRate());
    }

    public function testGetSuccessRateWithOnlyCompleted(): void
    {
        $data = [
            'stats' => [
                'completed' => 100,
                'failed' => 0,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(100.0, $response->getSuccessRate());
    }

    public function testGetSuccessRateWithOnlyFailed(): void
    {
        $data = [
            'stats' => [
                'completed' => 0,
                'failed' => 50,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(0.0, $response->getSuccessRate());
    }

    public function testGetSuccessRateWithNoCompletedOrFailed(): void
    {
        $data = [
            'stats' => [
                'completed' => 0,
                'failed' => 0,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(100.0, $response->getSuccessRate());
    }

    public function testGetSuccessRateWithDecimalResult(): void
    {
        $data = [
            'stats' => [
                'completed' => 2,
                'failed' => 1,
            ],
        ];

        $response = RefreshQueueResponse::fromArray($data);

        $this->assertEquals(66.67, $response->getSuccessRate());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new RefreshQueueResponse(
            pending: 10,
            queueCapacity: 500,
            workers: 8,
            queued: 200,
            completed: 180,
            failed: 5,
            duplicates: 10,
            queueFull: 5
        );

        $this->assertEquals(10, $response->pending);
        $this->assertEquals(500, $response->queueCapacity);
        $this->assertEquals(8, $response->workers);
        $this->assertEquals(200, $response->queued);
        $this->assertEquals(180, $response->completed);
        $this->assertEquals(5, $response->failed);
        $this->assertEquals(10, $response->duplicates);
        $this->assertEquals(5, $response->queueFull);
    }
}
