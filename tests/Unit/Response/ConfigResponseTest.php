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
use Qoliber\Trident\Response\ConfigResponse;

class ConfigResponseTest extends TestCase
{
    public function testFromArrayWithFullConfig(): void
    {
        $data = [
            'server' => [
                'listen' => ':8080',
                'admin_listen' => ':9100',
            ],
            'cache' => [
                'default_ttl' => '1h',
                'max_size' => '1GB',
            ],
            'backends' => [
                'origin' => [
                    'url' => 'http://localhost:3000',
                ],
            ],
        ];

        $response = ConfigResponse::fromArray($data);

        $this->assertEquals($data, $response->config);
        $this->assertIsArray($response->config);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = ConfigResponse::fromArray([]);

        $this->assertEmpty($response->config);
    }

    public function testGetWithDotNotation(): void
    {
        $data = [
            'server' => [
                'listen' => ':8080',
                'admin_listen' => ':9100',
            ],
            'cache' => [
                'default_ttl' => '1h',
            ],
        ];

        $response = ConfigResponse::fromArray($data);

        $this->assertEquals(':8080', $response->get('server.listen'));
        $this->assertEquals(':9100', $response->get('server.admin_listen'));
        $this->assertEquals('1h', $response->get('cache.default_ttl'));
    }

    public function testGetWithDefaultValue(): void
    {
        $data = [
            'server' => [
                'listen' => ':8080',
            ],
        ];

        $response = ConfigResponse::fromArray($data);

        $this->assertEquals('default', $response->get('nonexistent.key', 'default'));
        $this->assertEquals('fallback', $response->get('server.missing', 'fallback'));
    }

    public function testGetWithNonExistentPath(): void
    {
        $data = [
            'server' => [
                'listen' => ':8080',
            ],
        ];

        $response = ConfigResponse::fromArray($data);

        $this->assertNull($response->get('nonexistent'));
        $this->assertNull($response->get('deep.nested.path'));
    }

    public function testGetTopLevelKey(): void
    {
        $data = [
            'server' => [
                'listen' => ':8080',
            ],
            'version' => '1.0.0',
        ];

        $response = ConfigResponse::fromArray($data);

        $this->assertEquals(['listen' => ':8080'], $response->get('server'));
        $this->assertEquals('1.0.0', $response->get('version'));
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $config = [
            'key' => 'value',
            'nested' => ['inner' => 'data'],
        ];

        $response = new ConfigResponse(config: $config);

        $this->assertEquals($config, $response->config);
    }
}
