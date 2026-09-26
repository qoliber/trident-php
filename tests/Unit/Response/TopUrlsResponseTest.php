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
use Qoliber\Trident\Response\TopUrlsResponse;

class TopUrlsResponseTest extends TestCase
{
    public function testFromArrayWithFullData(): void
    {
        $data = [
            'window_secs' => 3600,
            'tracked_urls' => 100,
            'sort' => 'requests',
            'urls' => [
                [
                    'path' => '/products/1',
                    'requests' => 1000,
                    'bytes' => 50000,
                    'hits' => 900,
                    'misses' => 100,
                    'errors' => 0,
                    'hit_ratio' => 0.9,
                    'avg_duration_ms' => 15.5,
                ],
                [
                    'path' => '/products/2',
                    'requests' => 500,
                    'bytes' => 25000,
                    'hits' => 450,
                    'misses' => 50,
                    'errors' => 0,
                    'hit_ratio' => 0.9,
                    'avg_duration_ms' => 12.3,
                ],
            ],
            'totals' => [
                'path' => '',
                'requests' => 1500,
                'bytes' => 75000,
                'hits' => 1350,
                'misses' => 150,
                'errors' => 0,
                'hit_ratio' => 0.9,
                'avg_duration_ms' => 14.2,
            ],
        ];

        $response = TopUrlsResponse::fromArray($data);

        $this->assertEquals(3600, $response->windowSecs);
        $this->assertEquals(100, $response->trackedUrls);
        $this->assertEquals('requests', $response->sort);
        $this->assertCount(2, $response->urls);
        $this->assertEquals('/products/1', $response->urls[0]['path']);
        $this->assertEquals(1000, $response->urls[0]['requests']);
        $this->assertIsArray($response->totals);
        $this->assertEquals(1500, $response->totals['requests']);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $response = TopUrlsResponse::fromArray([]);

        $this->assertEquals(0, $response->windowSecs);
        $this->assertEquals(0, $response->trackedUrls);
        $this->assertEquals('requests', $response->sort);
        $this->assertEmpty($response->urls);
        $this->assertNull($response->totals);
    }

    public function testFromArrayWithDifferentSortOptions(): void
    {
        $data = [
            'sort' => 'bytes',
            'urls' => [],
        ];

        $response = TopUrlsResponse::fromArray($data);

        $this->assertEquals('bytes', $response->sort);
    }

    public function testFromArrayWithNoTotals(): void
    {
        $data = [
            'window_secs' => 1800,
            'tracked_urls' => 50,
            'sort' => 'hits',
            'urls' => [
                ['path' => '/api/v1', 'requests' => 200],
            ],
        ];

        $response = TopUrlsResponse::fromArray($data);

        $this->assertEquals(1800, $response->windowSecs);
        $this->assertNull($response->totals);
        $this->assertCount(1, $response->urls);
    }

    public function testConstructorPropertiesAreReadonly(): void
    {
        $urls = [['path' => '/test', 'requests' => 100]];
        $totals = ['requests' => 100];

        $response = new TopUrlsResponse(
            windowSecs: 7200,
            trackedUrls: 200,
            sort: 'misses',
            urls: $urls,
            totals: $totals
        );

        $this->assertEquals(7200, $response->windowSecs);
        $this->assertEquals(200, $response->trackedUrls);
        $this->assertEquals('misses', $response->sort);
        $this->assertEquals($urls, $response->urls);
        $this->assertEquals($totals, $response->totals);
    }
}
