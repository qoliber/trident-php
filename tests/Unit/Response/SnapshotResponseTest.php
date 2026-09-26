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
use Qoliber\Trident\Response\SnapshotResponse;

class SnapshotResponseTest extends TestCase
{
    public function testFromArraySuccess(): void
    {
        $data = [
            'success' => true,
            'path' => '/var/cache/trident/snapshot_20240115.dat',
            'entry_count' => 5000,
            'file_size' => 52428800, // 50 MB
            'duration_ms' => 1500,
            'compressed' => true,
        ];

        $response = SnapshotResponse::fromArray($data);

        $this->assertTrue($response->success);
        $this->assertEquals('/var/cache/trident/snapshot_20240115.dat', $response->path);
        $this->assertEquals(5000, $response->entryCount);
        $this->assertEquals(52428800, $response->fileSize);
        $this->assertEquals(1500, $response->durationMs);
        $this->assertTrue($response->compressed);
        $this->assertNull($response->error);
    }

    public function testFromArrayFailure(): void
    {
        $data = [
            'success' => false,
            'error' => 'Permission denied: cannot write to /var/cache/trident',
        ];

        $response = SnapshotResponse::fromArray($data);

        $this->assertFalse($response->success);
        $this->assertNull($response->path);
        $this->assertEquals('Permission denied: cannot write to /var/cache/trident', $response->error);
    }

    public function testIsSuccess(): void
    {
        $success = SnapshotResponse::fromArray(['success' => true]);
        $this->assertTrue($success->isSuccess());

        $failure = SnapshotResponse::fromArray(['success' => false]);
        $this->assertFalse($failure->isSuccess());
    }

    public function testHasError(): void
    {
        $withError = SnapshotResponse::fromArray([
            'success' => false,
            'error' => 'Something went wrong',
        ]);
        $this->assertTrue($withError->hasError());

        $noError = SnapshotResponse::fromArray(['success' => true]);
        $this->assertFalse($noError->hasError());
    }

    public function testGetters(): void
    {
        $response = SnapshotResponse::fromArray([
            'success' => true,
            'path' => '/tmp/snapshot.dat',
            'entry_count' => 100,
            'file_size' => 1024,
            'duration_ms' => 50,
            'compressed' => false,
        ]);

        $this->assertEquals('/tmp/snapshot.dat', $response->getPath());
        $this->assertEquals(100, $response->getEntryCount());
        $this->assertEquals(1024, $response->getFileSize());
        $this->assertEquals(50, $response->getDurationMs());
        $this->assertFalse($response->isCompressed());
        $this->assertNull($response->getError());
    }

    public function testGetFileSizeFormatted(): void
    {
        $testCases = [
            [500, '500 B'],
            [1024, '1 KB'],
            [1536, '1.5 KB'],
            [1048576, '1 MB'],
            [52428800, '50 MB'],
            [1073741824, '1 GB'],
        ];

        foreach ($testCases as [$size, $expected]) {
            $response = SnapshotResponse::fromArray([
                'success' => true,
                'file_size' => $size,
            ]);

            $this->assertEquals($expected, $response->getFileSizeFormatted());
        }
    }

    public function testGetFileSizeFormattedNull(): void
    {
        $response = SnapshotResponse::fromArray(['success' => true]);

        $this->assertNull($response->getFileSizeFormatted());
    }

    public function testToArraySuccess(): void
    {
        $response = SnapshotResponse::fromArray([
            'success' => true,
            'path' => '/tmp/test.dat',
            'entry_count' => 50,
            'file_size' => 2048,
            'duration_ms' => 100,
            'compressed' => true,
        ]);

        $output = $response->toArray();

        $this->assertTrue($output['success']);
        $this->assertEquals('/tmp/test.dat', $output['path']);
        $this->assertEquals(50, $output['entry_count']);
        $this->assertEquals(2048, $output['file_size']);
        $this->assertEquals(100, $output['duration_ms']);
        $this->assertTrue($output['compressed']);
        $this->assertArrayNotHasKey('error', $output);
    }

    public function testToArrayFailure(): void
    {
        $response = SnapshotResponse::fromArray([
            'success' => false,
            'error' => 'Disk full',
        ]);

        $output = $response->toArray();

        $this->assertFalse($output['success']);
        $this->assertEquals('Disk full', $output['error']);
        $this->assertArrayNotHasKey('path', $output);
    }

    public function testDefaultValues(): void
    {
        $response = SnapshotResponse::fromArray([]);

        $this->assertFalse($response->success);
        $this->assertNull($response->path);
        $this->assertNull($response->entryCount);
        $this->assertNull($response->fileSize);
        $this->assertNull($response->durationMs);
        $this->assertNull($response->compressed);
        $this->assertNull($response->error);
    }
}
