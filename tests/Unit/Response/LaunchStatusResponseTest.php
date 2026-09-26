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
use Qoliber\Trident\Response\LaunchStatusResponse;

class LaunchStatusResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'progress' => [
                'total' => 100,
                'completed' => 50,
                'failed' => 5,
                'pending' => 45,
                'percent' => 50,
            ],
            'current_url' => 'https://example.com/page-50',
            'maintenance_active' => true,
            'can_complete' => false,
            'can_abort' => true,
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertEquals('launch-123', $response->launchId);
        $this->assertEquals('warming', $response->status);
        $this->assertTrue($response->isWarming());
        $this->assertEquals('https://example.com/page-50', $response->currentUrl);
        $this->assertTrue($response->maintenanceActive);
        $this->assertFalse($response->canComplete);
        $this->assertTrue($response->canAbort);
    }

    public function testFromArrayWithStatusReady(): void
    {
        $data = [
            'launch_id' => 'launch-456',
            'status' => 'ready',
            'can_complete' => true,
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertTrue($response->isReady());
        $this->assertFalse($response->isWarming());
        $this->assertFalse($response->isCompleted());
        $this->assertTrue($response->canComplete);
    }

    public function testFromArrayWithStatusCompleted(): void
    {
        $data = [
            'launch_id' => 'launch-789',
            'status' => 'completed',
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertTrue($response->isCompleted());
        $this->assertFalse($response->isWarming());
        $this->assertFalse($response->isReady());
    }

    public function testFromArrayWithStatusFailed(): void
    {
        $data = [
            'launch_id' => 'launch-failed',
            'status' => 'failed',
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertTrue($response->isFailed());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = LaunchStatusResponse::fromArray([]);

        $this->assertEquals('', $response->launchId);
        $this->assertEquals('unknown', $response->status);
        $this->assertNull($response->progress);
        $this->assertNull($response->currentUrl);
        $this->assertFalse($response->maintenanceActive);
        $this->assertFalse($response->canComplete);
        $this->assertTrue($response->canAbort);
    }

    public function testProgressHelperMethods(): void
    {
        $data = [
            'launch_id' => 'launch-progress',
            'status' => 'warming',
            'progress' => [
                'total' => 200,
                'completed' => 150,
                'failed' => 10,
                'pending' => 40,
                'percent' => 75,
            ],
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertEquals(75, $response->getProgressPercent());
        $this->assertEquals(150, $response->getCompletedCount());
        $this->assertEquals(200, $response->getTotalCount());
        $this->assertEquals(10, $response->getFailedCount());
        $this->assertEquals(40, $response->getPendingCount());
    }

    public function testProgressHelperMethodsWithNoProgress(): void
    {
        $data = [
            'launch_id' => 'launch-no-progress',
            'status' => 'warming',
        ];

        $response = LaunchStatusResponse::fromArray($data);

        $this->assertEquals(0, $response->getProgressPercent());
        $this->assertEquals(0, $response->getCompletedCount());
        $this->assertEquals(0, $response->getTotalCount());
        $this->assertEquals(0, $response->getFailedCount());
        $this->assertEquals(0, $response->getPendingCount());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $progress = [
            'total' => 100,
            'completed' => 100,
            'failed' => 0,
            'pending' => 0,
            'percent' => 100,
        ];

        $response = new LaunchStatusResponse(
            launchId: 'test-launch',
            status: 'completed',
            progress: $progress,
            currentUrl: null,
            maintenanceActive: false,
            canComplete: false,
            canAbort: false
        );

        $this->assertEquals('test-launch', $response->launchId);
        $this->assertEquals('completed', $response->status);
        $this->assertEquals($progress, $response->progress);
        $this->assertNull($response->currentUrl);
        $this->assertFalse($response->maintenanceActive);
        $this->assertFalse($response->canComplete);
        $this->assertFalse($response->canAbort);
    }
}
