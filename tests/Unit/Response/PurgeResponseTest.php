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
use Qoliber\Trident\Response\PurgeResponse;

class PurgeResponseTest extends TestCase
{
    public function testFromArrayWithSuccess(): void
    {
        $data = [
            'success' => true,
            'purged' => 5,
            'message' => 'Cache purged successfully',
        ];

        $response = PurgeResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(5, $response->getPurgedCount());
        $this->assertEquals('Cache purged successfully', $response->message);
    }

    public function testFromArrayWithStatusOk(): void
    {
        $data = [
            'status' => 'ok',
            'purged_count' => 10,
        ];

        $response = PurgeResponse::fromArray($data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(10, $response->getPurgedCount());
    }

    public function testFromArrayWithCount(): void
    {
        $data = [
            'success' => true,
            'count' => 3,
        ];

        $response = PurgeResponse::fromArray($data);

        $this->assertEquals(3, $response->getPurgedCount());
    }

    public function testFromArrayWithDetails(): void
    {
        $data = [
            'success' => true,
            'purged' => 2,
            'details' => [
                'tags' => ['product.1', 'product.2'],
            ],
        ];

        $response = PurgeResponse::fromArray($data);

        $this->assertIsArray($response->details);
        $this->assertArrayHasKey('tags', $response->details);
    }
}
