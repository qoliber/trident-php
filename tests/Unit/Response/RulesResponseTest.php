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
use Qoliber\Trident\Response\RulesResponse;

class RulesResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'request_rules' => 3,
            'response_rules' => 2,
            'request' => [
                ['name' => 'add_auth', 'priority' => 100, 'enabled' => true, 'evaluations' => 1000, 'matches' => 500],
                ['name' => 'block_admin', 'priority' => 90, 'enabled' => true, 'evaluations' => 1000, 'matches' => 50],
                ['name' => 'normalize', 'priority' => 50, 'enabled' => false, 'evaluations' => 0, 'matches' => 0],
            ],
            'response' => [
                ['name' => 'add_headers', 'priority' => 100, 'enabled' => true, 'evaluations' => 800, 'matches' => 800],
                ['name' => 'strip_debug', 'priority' => 50, 'enabled' => true, 'evaluations' => 800, 'matches' => 200],
            ],
        ];

        $response = RulesResponse::fromArray($data);

        $this->assertEquals(3, $response->requestRules);
        $this->assertEquals(2, $response->responseRules);
        $this->assertEquals(5, $response->getTotalRules());
        $this->assertCount(3, $response->request);
        $this->assertCount(2, $response->response);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = RulesResponse::fromArray([]);

        $this->assertEquals(0, $response->requestRules);
        $this->assertEquals(0, $response->responseRules);
        $this->assertEquals(0, $response->getTotalRules());
        $this->assertEmpty($response->request);
        $this->assertEmpty($response->response);
    }

    public function testGetTotalEvaluations(): void
    {
        $data = [
            'request_rules' => 2,
            'response_rules' => 1,
            'request' => [
                ['name' => 'rule1', 'priority' => 100, 'enabled' => true, 'evaluations' => 500, 'matches' => 100],
                ['name' => 'rule2', 'priority' => 50, 'enabled' => true, 'evaluations' => 300, 'matches' => 50],
            ],
            'response' => [
                ['name' => 'resp1', 'priority' => 100, 'enabled' => true, 'evaluations' => 200, 'matches' => 200],
            ],
        ];

        $response = RulesResponse::fromArray($data);

        $this->assertEquals(1000, $response->getTotalEvaluations());
    }

    public function testGetTotalMatches(): void
    {
        $data = [
            'request_rules' => 2,
            'response_rules' => 1,
            'request' => [
                ['name' => 'rule1', 'priority' => 100, 'enabled' => true, 'evaluations' => 500, 'matches' => 100],
                ['name' => 'rule2', 'priority' => 50, 'enabled' => true, 'evaluations' => 300, 'matches' => 50],
            ],
            'response' => [
                ['name' => 'resp1', 'priority' => 100, 'enabled' => true, 'evaluations' => 200, 'matches' => 75],
            ],
        ];

        $response = RulesResponse::fromArray($data);

        $this->assertEquals(225, $response->getTotalMatches());
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $request = [['name' => 'test', 'priority' => 1, 'enabled' => true, 'evaluations' => 10, 'matches' => 5]];
        $responseRules = [];

        $response = new RulesResponse(
            requestRules: 1,
            responseRules: 0,
            request: $request,
            response: $responseRules
        );

        $this->assertEquals(1, $response->requestRules);
        $this->assertEquals(0, $response->responseRules);
        $this->assertEquals($request, $response->request);
        $this->assertEquals($responseRules, $response->response);
    }
}
