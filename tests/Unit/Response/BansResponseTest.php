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
use Qoliber\Trident\Response\BansResponse;

class BansResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'total' => 3,
            'active' => 2,
            'bans' => [
                [
                    'ban_type' => 'url',
                    'pattern' => '^/admin/.*',
                    'affected' => 50,
                    'created_at' => '2024-01-15T10:30:00Z',
                    'age_secs' => 3600,
                    'active' => true,
                ],
                [
                    'ban_type' => 'tag',
                    'pattern' => 'product.*',
                    'affected' => 100,
                    'created_at' => '2024-01-15T11:00:00Z',
                    'age_secs' => 1800,
                    'active' => true,
                ],
                [
                    'ban_type' => 'url',
                    'pattern' => '^/api/v1/.*',
                    'affected' => 25,
                    'created_at' => '2024-01-14T10:00:00Z',
                    'age_secs' => 90000,
                    'active' => false,
                ],
            ],
        ];

        $response = BansResponse::fromArray($data);

        $this->assertEquals(3, $response->total);
        $this->assertEquals(2, $response->active);
        $this->assertCount(3, $response->bans);
        $this->assertEquals('url', $response->bans[0]['ban_type']);
        $this->assertEquals('^/admin/.*', $response->bans[0]['pattern']);
        $this->assertEquals(50, $response->bans[0]['affected']);
        $this->assertTrue($response->bans[0]['active']);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = BansResponse::fromArray([]);

        $this->assertEquals(0, $response->total);
        $this->assertEquals(0, $response->active);
        $this->assertEmpty($response->bans);
    }

    public function testHasActiveBansWithActiveBans(): void
    {
        $data = [
            'total' => 5,
            'active' => 3,
            'bans' => [],
        ];

        $response = BansResponse::fromArray($data);

        $this->assertTrue($response->hasActiveBans());
    }

    public function testHasActiveBansWithNoActiveBans(): void
    {
        $data = [
            'total' => 5,
            'active' => 0,
            'bans' => [],
        ];

        $response = BansResponse::fromArray($data);

        $this->assertFalse($response->hasActiveBans());
    }

    public function testHasActiveBansWithEmptyBans(): void
    {
        $response = BansResponse::fromArray([]);

        $this->assertFalse($response->hasActiveBans());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $bans = [
            [
                'ban_type' => 'url',
                'pattern' => '/test',
                'affected' => 10,
                'created_at' => '2024-01-01T00:00:00Z',
                'age_secs' => 100,
                'active' => true,
            ],
        ];

        $response = new BansResponse(
            total: 1,
            active: 1,
            bans: $bans
        );

        $this->assertEquals(1, $response->total);
        $this->assertEquals(1, $response->active);
        $this->assertEquals($bans, $response->bans);
    }
}
