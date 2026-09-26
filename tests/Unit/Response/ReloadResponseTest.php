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
use Qoliber\Trident\Response\ReloadResponse;

class ReloadResponseTest extends TestCase
{
    public function testFromArrayWithSuccessTrue(): void
    {
        $data = [
            'success' => true,
            'message' => 'Configuration reloaded successfully',
        ];

        $response = ReloadResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('Configuration reloaded successfully', $response->message);
    }

    public function testFromArrayWithStatusOk(): void
    {
        $data = [
            'status' => 'ok',
        ];

        $response = ReloadResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
    }

    public function testFromArrayWithStatusReloaded(): void
    {
        $data = [
            'status' => 'reloaded',
            'message' => 'Hot reload complete',
        ];

        $response = ReloadResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('reloaded', $response->status);
        $this->assertEquals('Hot reload complete', $response->message);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = ReloadResponse::fromArray([]);

        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->status);
        $this->assertNull($response->message);
    }

    public function testFromArrayWithFailure(): void
    {
        $data = [
            'success' => false,
            'message' => 'Invalid configuration',
        ];

        $response = ReloadResponse::fromArray($data);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Invalid configuration', $response->message);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new ReloadResponse(
            success: true,
            status: 'reloaded',
            message: 'Config updated'
        );

        $this->assertTrue($response->success);
        $this->assertEquals('reloaded', $response->status);
        $this->assertEquals('Config updated', $response->message);
    }
}
