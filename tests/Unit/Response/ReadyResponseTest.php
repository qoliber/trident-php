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
use Qoliber\Trident\Response\ReadyResponse;

class ReadyResponseTest extends TestCase
{
    public function testFromArrayWithReadyStatus(): void
    {
        $data = [
            'ready' => true,
            'status' => 'ready',
            'backends' => [
                'origin' => ['healthy' => true, 'name' => 'origin'],
                'api' => ['healthy' => true, 'name' => 'api'],
            ],
        ];

        $response = ReadyResponse::fromArray($data);

        $this->assertTrue($response->isReady());
        $this->assertEquals('ready', $response->status);
        $this->assertCount(2, $response->backends);
    }

    public function testFromArrayWithNotReadyStatus(): void
    {
        $data = [
            'ready' => false,
            'status' => 'not_ready',
            'backends' => [
                'origin' => ['healthy' => false, 'name' => 'origin'],
            ],
        ];

        $response = ReadyResponse::fromArray($data);

        $this->assertFalse($response->isReady());
        $this->assertEquals('not_ready', $response->status);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = ReadyResponse::fromArray([]);

        $this->assertFalse($response->isReady());
        $this->assertEquals('not_ready', $response->status);
        $this->assertEmpty($response->backends);
    }

    public function testFromArrayWithStatusReady(): void
    {
        $data = [
            'status' => 'ready',
        ];

        $response = ReadyResponse::fromArray($data);

        $this->assertTrue($response->isReady());
        $this->assertEquals('ready', $response->status);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $backends = [
            'default' => ['healthy' => true, 'name' => 'default'],
        ];

        $response = new ReadyResponse(
            ready: true,
            status: 'ready',
            backends: $backends
        );

        $this->assertTrue($response->ready);
        $this->assertEquals('ready', $response->status);
        $this->assertEquals($backends, $response->backends);
    }
}
