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
use Qoliber\Trident\Response\LaunchResponse;

class LaunchResponseTest extends TestCase
{
    public function testFromArrayWithLaunchId(): void
    {
        $data = [
            'launch_id' => 'launch-123',
            'status' => 'warming',
            'started_at' => '2024-01-15T10:00:00Z',
            'urls_total' => 100,
            'urls_completed' => 25,
            'maintenance_active' => true,
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('launch-123', $response->launchId);
        $this->assertEquals('warming', $response->status);
        $this->assertEquals('2024-01-15T10:00:00Z', $response->startedAt);
        $this->assertEquals(100, $response->urlsTotal);
        $this->assertEquals(25, $response->urlsCompleted);
        $this->assertTrue($response->isMaintenanceActive());
    }

    public function testFromArrayWithStatusWarming(): void
    {
        $data = [
            'status' => 'warming',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isWarming());
        $this->assertFalse($response->isReady());
        $this->assertFalse($response->isCompleted());
        $this->assertFalse($response->isAborted());
    }

    public function testFromArrayWithStatusReady(): void
    {
        $data = [
            'launch_id' => 'launch-456',
            'status' => 'ready',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isReady());
        $this->assertFalse($response->isWarming());
    }

    public function testFromArrayWithStatusCompleted(): void
    {
        $data = [
            'launch_id' => 'launch-789',
            'status' => 'completed',
            'message' => 'Launch completed successfully',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isCompleted());
        $this->assertEquals('Launch completed successfully', $response->message);
    }

    public function testFromArrayWithStatusAborted(): void
    {
        $data = [
            'launch_id' => 'launch-abort',
            'status' => 'aborted',
            'reason' => 'User requested abort',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isAborted());
        $this->assertEquals('User requested abort', $response->reason);
    }

    public function testFromArrayWithStatusFailed(): void
    {
        $data = [
            'status' => 'failed',
            'message' => 'Too many failures',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertFalse($response->isSuccess());
        $this->assertTrue($response->isFailed());
        $this->assertEquals('Too many failures', $response->message);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = LaunchResponse::fromArray([]);

        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->launchId);
        $this->assertNull($response->status);
        $this->assertNull($response->startedAt);
        $this->assertNull($response->urlsTotal);
        $this->assertNull($response->urlsCompleted);
    }

    public function testMaintenanceActiveDefaults(): void
    {
        $data = [
            'launch_id' => 'launch-no-maintenance',
            'status' => 'warming',
        ];

        $response = LaunchResponse::fromArray($data);

        $this->assertFalse($response->isMaintenanceActive());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new LaunchResponse(
            success: true,
            launchId: 'test-launch',
            status: 'ready',
            startedAt: '2024-01-01T00:00:00Z',
            urlsTotal: 50,
            urlsCompleted: 50,
            maintenanceActive: false,
            message: 'All good',
            reason: null
        );

        $this->assertTrue($response->success);
        $this->assertEquals('test-launch', $response->launchId);
        $this->assertEquals('ready', $response->status);
        $this->assertEquals(50, $response->urlsTotal);
        $this->assertEquals(50, $response->urlsCompleted);
        $this->assertEquals('All good', $response->message);
    }
}
