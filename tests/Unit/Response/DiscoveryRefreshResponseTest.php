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
use Qoliber\Trident\Response\DiscoveryRefreshResponse;

class DiscoveryRefreshResponseTest extends TestCase
{
    public function testFromArraySuccess(): void
    {
        $data = [
            'success' => true,
            'name' => 'api-backend',
            'ips' => ['192.168.1.1', '192.168.1.2', '192.168.1.3'],
            'message' => 'DNS refresh completed successfully',
        ];

        $response = DiscoveryRefreshResponse::fromArray($data);

        $this->assertTrue($response->success);
        $this->assertEquals('api-backend', $response->name);
        $this->assertCount(3, $response->ips);
        $this->assertEquals('DNS refresh completed successfully', $response->message);
        $this->assertNull($response->error);
    }

    public function testFromArrayFailure(): void
    {
        $data = [
            'success' => false,
            'name' => 'broken-backend',
            'ips' => [],
            'error' => 'DNS resolution failed: NXDOMAIN',
        ];

        $response = DiscoveryRefreshResponse::fromArray($data);

        $this->assertFalse($response->success);
        $this->assertEquals('broken-backend', $response->name);
        $this->assertEquals([], $response->ips);
        $this->assertEquals('DNS resolution failed: NXDOMAIN', $response->error);
    }

    public function testFromArrayAlternativeFieldNames(): void
    {
        $response = DiscoveryRefreshResponse::fromArray([
            'success' => true,
            'name' => 'test',
            'resolved_ips' => ['1.1.1.1', '2.2.2.2'],
        ]);

        $this->assertEquals(['1.1.1.1', '2.2.2.2'], $response->ips);
    }

    public function testIsSuccess(): void
    {
        $success = DiscoveryRefreshResponse::fromArray(['success' => true, 'name' => 'test']);
        $this->assertTrue($success->isSuccess());

        $failure = DiscoveryRefreshResponse::fromArray(['success' => false, 'name' => 'test']);
        $this->assertFalse($failure->isSuccess());
    }

    public function testGetIps(): void
    {
        $response = DiscoveryRefreshResponse::fromArray([
            'success' => true,
            'name' => 'test',
            'ips' => ['10.0.0.1', '10.0.0.2'],
        ]);

        $this->assertEquals(['10.0.0.1', '10.0.0.2'], $response->getIps());
    }

    public function testGetIpCount(): void
    {
        $response = DiscoveryRefreshResponse::fromArray([
            'success' => true,
            'name' => 'test',
            'ips' => ['1.1.1.1', '2.2.2.2', '3.3.3.3'],
        ]);

        $this->assertEquals(3, $response->getIpCount());
    }

    public function testToArray(): void
    {
        $data = [
            'success' => true,
            'name' => 'backend1',
            'ips' => ['1.1.1.1', '2.2.2.2'],
            'message' => 'Refresh complete',
            'error' => null,
        ];

        $response = DiscoveryRefreshResponse::fromArray($data);
        $output = $response->toArray();

        $this->assertTrue($output['success']);
        $this->assertEquals('backend1', $output['name']);
        $this->assertEquals(['1.1.1.1', '2.2.2.2'], $output['ips']);
        $this->assertEquals('Refresh complete', $output['message']);
        $this->assertNull($output['error']);
    }

    public function testDefaultValues(): void
    {
        $response = DiscoveryRefreshResponse::fromArray([]);

        $this->assertTrue($response->success); // defaults to true
        $this->assertEquals('', $response->name);
        $this->assertEquals([], $response->ips);
        $this->assertNull($response->message);
        $this->assertNull($response->error);
    }
}
