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
use Qoliber\Trident\Response\BackendActionResponse;

class BackendActionResponseTest extends TestCase
{
    public function testFromArrayWithSuccessTrue(): void
    {
        $data = [
            'success' => true,
            'backend' => 'origin',
            'action' => 'drain',
            'message' => 'Backend drained successfully',
        ];

        $response = BackendActionResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('origin', $response->backend);
        $this->assertEquals('drain', $response->action);
        $this->assertEquals('Backend drained successfully', $response->message);
    }

    public function testFromArrayWithStatusOk(): void
    {
        $data = [
            'status' => 'ok',
            'backend' => 'api',
            'action' => 'restore',
        ];

        $response = BackendActionResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('api', $response->backend);
        $this->assertEquals('restore', $response->action);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = BackendActionResponse::fromArray([]);

        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->backend);
        $this->assertNull($response->action);
        $this->assertNull($response->message);
    }

    public function testFromArrayWithFailure(): void
    {
        $data = [
            'success' => false,
            'backend' => 'missing',
            'message' => 'Backend not found',
        ];

        $response = BackendActionResponse::fromArray($data);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('missing', $response->backend);
        $this->assertEquals('Backend not found', $response->message);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new BackendActionResponse(
            success: true,
            backend: 'test-backend',
            action: 'drain',
            message: 'Drained'
        );

        $this->assertTrue($response->success);
        $this->assertEquals('test-backend', $response->backend);
        $this->assertEquals('drain', $response->action);
        $this->assertEquals('Drained', $response->message);
    }
}
