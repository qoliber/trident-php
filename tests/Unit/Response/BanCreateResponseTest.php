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
use Qoliber\Trident\Response\BanCreateResponse;

class BanCreateResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'success' => true,
            'id' => 'ban-123',
            'pattern' => '^/admin/.*',
            'expires' => '2024-01-20T10:00:00Z',
            'message' => 'Ban created successfully',
        ];

        $response = BanCreateResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ban-123', $response->id);
        $this->assertEquals('^/admin/.*', $response->pattern);
        $this->assertEquals('2024-01-20T10:00:00Z', $response->expires);
        $this->assertEquals('Ban created successfully', $response->message);
    }

    public function testFromArrayWithStatusOk(): void
    {
        $data = [
            'status' => 'ok',
            'id' => 'ban-456',
            'pattern' => '/api/.*',
        ];

        $response = BanCreateResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ban-456', $response->id);
    }

    public function testFromArrayWithIdOnly(): void
    {
        $data = [
            'id' => 'ban-789',
        ];

        $response = BanCreateResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ban-789', $response->id);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = BanCreateResponse::fromArray([]);

        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->id);
        $this->assertNull($response->pattern);
        $this->assertNull($response->expires);
        $this->assertNull($response->message);
    }

    public function testFromArrayWithFailure(): void
    {
        $data = [
            'success' => false,
            'message' => 'Invalid pattern',
        ];

        $response = BanCreateResponse::fromArray($data);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Invalid pattern', $response->message);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new BanCreateResponse(
            success: true,
            id: 'test-id',
            pattern: '/test/.*',
            expires: '2024-12-31T23:59:59Z',
            message: 'Created'
        );

        $this->assertTrue($response->success);
        $this->assertEquals('test-id', $response->id);
        $this->assertEquals('/test/.*', $response->pattern);
        $this->assertEquals('2024-12-31T23:59:59Z', $response->expires);
        $this->assertEquals('Created', $response->message);
    }
}
