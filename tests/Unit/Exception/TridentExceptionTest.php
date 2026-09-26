<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Exception\TridentException;
use RuntimeException;

class TridentExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new TridentException('Test error');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testMessage(): void
    {
        $message = 'Failed to connect to Trident';
        $exception = new TridentException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCode(): void
    {
        $exception = new TridentException('Error', 500);

        $this->assertEquals(500, $exception->getCode());
    }

    public function testPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new TridentException('Trident error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(TridentException::class);
        $this->expectExceptionMessage('Connection refused');
        $this->expectExceptionCode(503);

        throw new TridentException('Connection refused', 503);
    }

    public function testCanBeCaught(): void
    {
        $caught = false;

        try {
            throw new TridentException('Test');
        } catch (TridentException $e) {
            $caught = true;
        }

        $this->assertTrue($caught);
    }

    public function testCanBeCaughtAsRuntimeException(): void
    {
        $caught = false;

        try {
            throw new TridentException('Test');
        } catch (RuntimeException $e) {
            $caught = true;
        }

        $this->assertTrue($caught);
    }

    public function testWithHttpStatusCode(): void
    {
        $exception = new TridentException('Not Found', 404);

        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals('Not Found', $exception->getMessage());
    }

    public function testWithConnectionError(): void
    {
        $socketError = new \Exception('Connection refused');
        $exception = new TridentException(
            'Failed to connect to Trident: Connection refused',
            0,
            $socketError
        );

        $this->assertStringContainsString('Connection refused', $exception->getMessage());
        $this->assertSame($socketError, $exception->getPrevious());
    }
}
