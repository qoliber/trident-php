<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Events\TridentEvent;

class TridentEventTest extends TestCase
{
    public function testConstructor(): void
    {
        $event = new TridentEvent(
            'request',
            ['url' => '/test', 'method' => 'GET'],
            'event-123'
        );

        $this->assertEquals('request', $event->type);
        $this->assertEquals(['url' => '/test', 'method' => 'GET'], $event->data);
        $this->assertEquals('event-123', $event->id);
    }

    public function testGetters(): void
    {
        $event = new TridentEvent(
            'cache',
            ['hit' => true, 'key' => '/products/1'],
            'cache-456'
        );

        $this->assertEquals('cache', $event->getType());
        $this->assertEquals(['hit' => true, 'key' => '/products/1'], $event->getData());
        $this->assertEquals('cache-456', $event->getId());
    }

    public function testGet(): void
    {
        $event = new TridentEvent('test', [
            'status' => 200,
            'message' => 'OK',
            'nested' => ['key' => 'value'],
        ]);

        $this->assertEquals(200, $event->get('status'));
        $this->assertEquals('OK', $event->get('message'));
        $this->assertEquals(['key' => 'value'], $event->get('nested'));
        $this->assertNull($event->get('nonexistent'));
        $this->assertEquals('default', $event->get('nonexistent', 'default'));
    }

    public function testIsRequest(): void
    {
        $requestByType = new TridentEvent('request', []);
        $this->assertTrue($requestByType->isRequest());

        $requestByUrl = new TridentEvent('other', ['url' => '/test']);
        $this->assertTrue($requestByUrl->isRequest());

        $notRequest = new TridentEvent('cache', ['hit' => true]);
        $this->assertFalse($notRequest->isRequest());
    }

    public function testIsCache(): void
    {
        $hit = new TridentEvent('hit', []);
        $this->assertTrue($hit->isCache());

        $miss = new TridentEvent('miss', []);
        $this->assertTrue($miss->isCache());

        $eviction = new TridentEvent('eviction', []);
        $this->assertTrue($eviction->isCache());

        $cache = new TridentEvent('cache', []);
        $this->assertTrue($cache->isCache());

        $notCache = new TridentEvent('request', []);
        $this->assertFalse($notCache->isCache());
    }

    public function testIsBackend(): void
    {
        $backendByType = new TridentEvent('backend', []);
        $this->assertTrue($backendByType->isBackend());

        $backendByData = new TridentEvent('status', ['backend' => 'api']);
        $this->assertTrue($backendByData->isBackend());

        $notBackend = new TridentEvent('cache', ['hit' => true]);
        $this->assertFalse($notBackend->isBackend());
    }

    public function testIsError(): void
    {
        $errorByType = new TridentEvent('error', []);
        $this->assertTrue($errorByType->isError());

        $errorByData = new TridentEvent('status', ['error' => 'Connection failed']);
        $this->assertTrue($errorByData->isError());

        $notError = new TridentEvent('request', ['status' => 200]);
        $this->assertFalse($notError->isError());
    }

    public function testIsLaunch(): void
    {
        $launchStart = new TridentEvent('launch_started', []);
        $this->assertTrue($launchStart->isLaunch());

        $launchProgress = new TridentEvent('launch_progress', ['launch_id' => 'abc123']);
        $this->assertTrue($launchProgress->isLaunch());

        $launchById = new TridentEvent('status', ['launch_id' => 'xyz789']);
        $this->assertTrue($launchById->isLaunch());

        $notLaunch = new TridentEvent('request', []);
        $this->assertFalse($notLaunch->isLaunch());
    }

    public function testToArray(): void
    {
        $event = new TridentEvent(
            'request',
            ['url' => '/test', 'status' => 200],
            'event-789'
        );

        $array = $event->toArray();

        $this->assertEquals('request', $array['type']);
        $this->assertEquals(['url' => '/test', 'status' => 200], $array['data']);
        $this->assertEquals('event-789', $array['id']);
    }

    public function testToArrayWithNullId(): void
    {
        $event = new TridentEvent('test', ['key' => 'value']);

        $array = $event->toArray();

        $this->assertEquals('test', $array['type']);
        $this->assertEquals(['key' => 'value'], $array['data']);
        $this->assertNull($array['id']);
    }
}
