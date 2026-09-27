<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Http;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Qoliber\Trident\Http\NetworkGuard;
use Qoliber\Trident\Http\TransportFactory;

final class NetworkGuardTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function addresses(): iterable
    {
        yield 'aws/gcp/azure metadata' => ['169.254.169.254', true];
        yield 'link-local v4' => ['169.254.1.1', true];
        yield 'link-local v6' => ['fe80::1', true];
        yield 'aws metadata v6' => ['fd00:ec2::254', true];
        yield 'alibaba metadata' => ['100.100.100.200', true];
        yield 'v4-mapped metadata' => ['::ffff:169.254.169.254', true];
        yield 'garbage' => ['not-an-ip', true];
        yield 'private 10/8 (Trident is internal)' => ['10.0.0.5', false];
        yield 'loopback' => ['127.0.0.1', false];
        yield 'public' => ['93.184.216.34', false];
        yield 'neighbour of alibaba' => ['100.100.100.201', false];
        yield 'fd00:ec2::253' => ['fd00:ec2::253', false];
        yield 'unspecified v4' => ['0.0.0.0', true];
        yield '0/8' => ['0.1.2.3', true];
        yield 'unspecified v6' => ['::', true];
        yield 'v6 loopback' => ['::1', false];
        yield 'v4-compatible metadata' => ['::169.254.169.254', true];
        yield 'v4-compatible private' => ['::10.0.0.5', false];
        yield 'NAT64 metadata' => ['64:ff9b::169.254.169.254', true];
        yield 'NAT64 public' => ['64:ff9b::93.184.216.34', false];
        yield '6to4 metadata' => ['2002:a9fe:a9fe::1', true];
        yield '6to4 public' => ['2002:5db8:d822::1', false];
    }

    #[DataProvider('addresses')]
    public function testForbiddenRanges(string $address, bool $forbidden): void
    {
        self::assertSame($forbidden, NetworkGuard::forbidden($address));
    }

    public function testMetadataNamesAreRefusedWithoutResolving(): void
    {
        $guard = new NetworkGuard(static fn (string $h): array => throw new \LogicException('resolved ' . $h));
        foreach (['metadata.google.internal', 'METADATA.google.internal.', 'metadata'] as $name) {
            try {
                $guard->address($name);
                self::fail($name . ' was allowed');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('metadata', $e->getMessage());
            }
        }
    }

    public function testAHostWithAnyForbiddenAddressIsRefused(): void
    {
        $guard = new NetworkGuard(static fn (): array => ['10.0.0.5', '169.254.169.254']);
        $this->expectException(\RuntimeException::class);
        $guard->address('rebind.example');
    }

    /**
     * DNS rebinding: the first lookup answers a harmless address, the second
     * the metadata service. The request must connect to the address that was
     * checked — pinned for curl — never look the name up again.
     */
    public function testTheCheckedAddressIsPinnedForTheConnection(): void
    {
        $answers = [['10.0.0.5'], ['169.254.169.254']];
        $guard = new NetworkGuard(static function () use (&$answers): array {
            return array_shift($answers) ?? [];
        });
        $seen = [];
        $handler = static function (RequestInterface $request, array $options) use (&$seen) {
            $seen[] = $options['curl'][\CURLOPT_RESOLVE] ?? null;

            return Create::promiseFor(new Response(200));
        };
        $client = (new TransportFactory(5.0, 2.0, $guard, \Closure::fromCallable($handler), true))->client();
        $client->sendRequest(new Request('GET', 'http://rebind.example:9301/api/version'));
        self::assertSame([['rebind.example:9301:10.0.0.5']], $seen);
    }

    public function testARefusedTargetIsNeverSent(): void
    {
        $sent = 0;
        $handler = static function () use (&$sent) {
            ++$sent;

            return Create::promiseFor(new Response(200));
        };
        $client = (new TransportFactory(5.0, 2.0, new NetworkGuard(static fn (): array => []), \Closure::fromCallable($handler), true))->client();
        try {
            $client->sendRequest(new Request('GET', 'http://169.254.169.254/latest/meta-data/'));
            self::fail('sent');
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            self::assertStringContainsString('refused', $e->getMessage());
        }
        self::assertSame(0, $sent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedUrls(): iterable
    {
        yield 'decimal' => ['http://2852039166/latest'];
        yield 'octal' => ['http://0251.0376.0251.0376/latest'];
        yield 'hex' => ['http://0xa9fea9fe/latest'];
        yield 'short form' => ['http://169.254.43518/latest'];
        yield 'unspecified v4' => ['http://0.0.0.0:9301/'];
        yield 'unspecified v6' => ['http://[::]:9301/'];
        yield 'v6 literal link-local' => ['http://[fe80::1]:9301/'];
        yield 'v6 literal NAT64 metadata' => ['http://[64:ff9b::a9fe:a9fe]:9301/'];
    }

    #[DataProvider('refusedUrls')]
    public function testNumericAndLiteralHostsAreJudgedWithoutResolving(string $url): void
    {
        $sent = 0;
        $handler = static function () use (&$sent) {
            ++$sent;

            return Create::promiseFor(new Response(200));
        };
        $guard = new NetworkGuard(static fn (string $h): array => throw new \LogicException('resolved ' . $h));
        $client = (new TransportFactory(5.0, 2.0, $guard, \Closure::fromCallable($handler), true))->client();
        try {
            $client->sendRequest(new Request('GET', $url));
            self::fail('sent');
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            self::assertStringContainsString('refused', $e->getMessage());
        }
        self::assertSame(0, $sent);
    }

    public function testNumericV4Spellings(): void
    {
        self::assertSame('169.254.169.254', NetworkGuard::numericV4('2852039166'));
        self::assertSame('169.254.169.254', NetworkGuard::numericV4('0251.0376.0251.0376'));
        self::assertSame('127.0.0.1', NetworkGuard::numericV4('127.1'));
        self::assertNull(NetworkGuard::numericV4('trident'));
        self::assertNull(NetworkGuard::numericV4('09.1.1.1'));
    }

    public function testEnvironmentProxiesAreOffAndRedirectsNotFollowed(): void
    {
        $seen = null;
        $handler = static function (RequestInterface $request, array $options) use (&$seen) {
            $seen = $options;

            return Create::promiseFor(new Response(302, ['Location' => 'http://elsewhere/']));
        };
        $client = (new TransportFactory(5.0, 2.0, new NetworkGuard(static fn (): array => ['10.0.0.5']), \Closure::fromCallable($handler), true))->client();
        $response = $client->sendRequest(new Request('GET', 'http://trident:9301/admin/status'));
        self::assertSame('*', $seen['curl'][\CURLOPT_NOPROXY] ?? null, 'no environment proxy for admin traffic');
        self::assertSame([], $seen['proxy'] ?? null);
        self::assertFalse($seen['allow_redirects']);
        self::assertSame(302, $response->getStatusCode(), 'the redirect is returned, not followed');
    }

    public function testAnExplicitProxyIsUsedAndPinningIsOff(): void
    {
        $seen = null;
        $handler = static function (RequestInterface $request, array $options) use (&$seen) {
            $seen = $options;

            return Create::promiseFor(new Response(200));
        };
        $client = (new TransportFactory(5.0, 2.0, new NetworkGuard(static fn (): array => ['10.0.0.5']), \Closure::fromCallable($handler), true, 'http://proxy.internal:3128'))->client();
        $client->sendRequest(new Request('GET', 'http://trident:9301/admin/status'));
        self::assertSame('http://proxy.internal:3128', $seen['proxy']);
        self::assertArrayNotHasKey(\CURLOPT_RESOLVE, $seen['curl'] ?? []);
        self::assertArrayNotHasKey(\CURLOPT_NOPROXY, $seen['curl'] ?? []);
    }

    public function testACustomHandlerMustDeclareItHonoursTheCurlOptions(): void
    {
        $this->expectException(\LogicException::class);
        new TransportFactory(5.0, 2.0, null, static fn () => Create::promiseFor(new Response(200)));
    }

    public function testTheGuardStillRefusesThroughAnExplicitProxy(): void
    {
        $handler = static fn () => Create::promiseFor(new Response(200));
        $client = (new TransportFactory(5.0, 2.0, new NetworkGuard(static fn (): array => []), \Closure::fromCallable($handler), true, 'http://proxy.internal:3128'))->client();
        $this->expectException(\Psr\Http\Client\ClientExceptionInterface::class);
        $client->sendRequest(new Request('GET', 'http://169.254.169.254/'));
    }
}
