<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\PurgeClient;
use Qoliber\Trident\Delivery\Psr18Transport;

final class Psr18TransportTest extends TestCase
{
    public function testSendsTheRequestAndReturnsStatusAndBody(): void
    {
        $client = new class implements ClientInterface {
            public ?RequestInterface $seen = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->seen = $request;
                return new Response(200, [], '{"purged":2,"mode":"hard","state":"applied"}');
            }
        };
        $factory = new HttpFactory();
        $purge = new PurgeClient(new Instance('e', 'http://edge:9301', 'tok'), new Psr18Transport($client, $factory, $factory));
        $attempt = $purge->purgeTags(['a', 'b'], 'hard');

        self::assertTrue($attempt->acknowledged());
        self::assertNotNull($client->seen);
        self::assertSame('POST', $client->seen->getMethod());
        self::assertSame('http://edge:9301/admin/purge/tags', (string) $client->seen->getUri());
        self::assertSame('Bearer tok', $client->seen->getHeaderLine('Authorization'));
        self::assertSame(['tags' => ['a', 'b'], 'mode' => 'hard'], json_decode((string) $client->seen->getBody(), true));
    }

    public function testNetworkErrorIsNoResponseNotAnException(): void
    {
        $client = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('Connection refused') extends \RuntimeException implements ClientExceptionInterface {
                };
            }
        };
        $factory = new HttpFactory();
        $transport = new Psr18Transport($client, $factory, $factory);
        self::assertSame(['status' => 0, 'body' => '', 'error' => 'Connection refused'], $transport->request('GET', 'http://x/', [], null));

        $attempt = (new PurgeClient(new Instance('e', 'http://x', 't'), $transport))->purgeTags(['a'], 'soft');
        self::assertFalse($attempt->acknowledged());
        self::assertTrue($attempt->unreachable);
        self::assertSame('no response — Connection refused', $attempt->failure);
    }
}
