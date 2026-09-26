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
use Qoliber\Trident\Response\RulesValidateResponse;

class RulesValidateResponseTest extends TestCase
{
    public function testFromArrayWithValidConfig(): void
    {
        $data = [
            'valid' => true,
            'request_rules' => 5,
            'response_rules' => 3,
        ];

        $response = RulesValidateResponse::fromArray($data);

        $this->assertTrue($response->isValid());
        $this->assertFalse($response->hasError());
        $this->assertEquals(5, $response->requestRules);
        $this->assertEquals(3, $response->responseRules);
        $this->assertEquals(8, $response->getTotalRules());
        $this->assertNull($response->error);
    }

    public function testFromArrayWithInvalidConfig(): void
    {
        $data = [
            'valid' => false,
            'request_rules' => 0,
            'response_rules' => 0,
            'error' => 'Invalid TOML syntax at line 42',
        ];

        $response = RulesValidateResponse::fromArray($data);

        $this->assertFalse($response->isValid());
        $this->assertTrue($response->hasError());
        $this->assertEquals('Invalid TOML syntax at line 42', $response->error);
        $this->assertEquals(0, $response->getTotalRules());
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = RulesValidateResponse::fromArray([]);

        $this->assertFalse($response->isValid());
        $this->assertFalse($response->hasError());
        $this->assertEquals(0, $response->requestRules);
        $this->assertEquals(0, $response->responseRules);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $response = new RulesValidateResponse(
            valid: true,
            requestRules: 10,
            responseRules: 5,
            error: null
        );

        $this->assertTrue($response->valid);
        $this->assertEquals(10, $response->requestRules);
        $this->assertEquals(5, $response->responseRules);
        $this->assertNull($response->error);
    }
}
