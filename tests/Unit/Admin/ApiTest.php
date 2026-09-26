<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Admin\Api;
use Qoliber\Trident\Admin\ApiError;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\Transport;
use Qoliber\Trident\Testing\FakeTransport;

final class ApiTest extends TestCase
{
    /** @var list<int> */
    private array $slept = [];

    private function api(FakeTransport|Transport $transport, string $token = 'secret'): Api
    {
        return new Api(
            new Instance('edge-1', 'http://edge-1:9301', $token),
            $transport,
            null,
            function (int $ms): void {
                $this->slept[] = $ms;
            }
        );
    }

    public function testSendsTheBearerTokenAndDecodesJson(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 200, '{"status":"ok","version":"1.8.0"}');
        $reply = $this->api($t)->call('GET', '/admin/status');

        self::assertSame(200, $reply['status']);
        self::assertSame('1.8.0', $reply['data']['version']);
        self::assertSame('http://edge-1:9301/admin/status', $t->requests[0]['url']);
        self::assertSame('Bearer secret', $t->requests[0]['headers']['Authorization']);
        self::assertSame('application/json', $t->requests[0]['headers']['Accept']);
        self::assertArrayNotHasKey('Content-Type', $t->requests[0]['headers'], 'no body, no Content-Type');
    }

    public function testNoTokenMeansNoAuthorizationHeader(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 200, '{}');
        $this->api($t, '')->call('GET', '/admin/health');
        self::assertArrayNotHasKey('Authorization', $t->requests[0]['headers']);
    }

    public function testQueryIsEncodedAndNullsDropped(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 200, '{}');
        $this->api($t)->call('GET', '/admin/cache/variants', ['url' => '/a b?x=1', 'host' => null]);
        self::assertSame('http://edge-1:9301/admin/cache/variants?url=%2Fa+b%3Fx%3D1', $t->requests[0]['url']);
    }

    public function testAnEmptyBodyIsAnEmptyJsonObject(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 200, '{}');
        $this->api($t)->call('POST', '/admin/reflect/enable', [], []);
        self::assertSame('{}', $t->requests[0]['body'], 'the engine rejects "[]" where it expects an object');
        self::assertSame('application/json', $t->requests[0]['headers']['Content-Type']);
    }

    public function testNoContentIsASuccess(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 204, '');
        $reply = $this->api($t)->call('POST', '/admin/warmer/cancel');
        self::assertSame(['success' => true, 'status_code' => 204], $reply['data']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function notTheAdminApi(): array
    {
        return [
            'redirect to https'       => [301, ''],
            'temporary redirect'      => [302, '<html>Moved</html>'],
            "a proxy's HTML page"     => [200, '<!doctype html><title>Welcome to nginx</title>'],
            'empty 200'               => [200, ''],
            'plain text 200'          => [200, 'OK'],
        ];
    }

    /**
     * The engine answers every admin call with JSON. Anything else used to
     * decode to `success: true`, so a screen reported "Launch started" or a
     * "reachable" empty dashboard when Trident never saw the request.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notTheAdminApi')]
    public function testAnythingButTheAdminApiIsAnError(int $status, string $body): void
    {
        $t = (new FakeTransport())->answer('edge-1', $status, $body);
        try {
            $this->api($t)->call('POST', '/admin/launch/start', [], []);
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertSame($status, $e->status());
            self::assertFalse($e->isFeatureDisabled());
        }
    }

    public function testAnErrorCarriesTheEngineCodeAndReadsAsDisabled(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 404, '{"error":"Reflect mode is not enabled","code":"REFLECT_DISABLED"}');
        try {
            $this->api($t)->call('GET', '/admin/reflect/status');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertSame(404, $e->status());
            self::assertSame('REFLECT_DISABLED', $e->engineCode);
            self::assertTrue($e->isFeatureDisabled());
            self::assertFalse($e->isUnreachable());
            self::assertSame('HTTP 404: Reflect mode is not enabled', $e->reason());
        }
    }

    public function testA503NotEnabledMessageReadsAsDisabled(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 503, '{"error":"Service Unavailable","message":"PathDenoiser not enabled"}');
        try {
            $this->api($t)->call('GET', '/admin/denoisers/path/zones');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertTrue($e->isFeatureDisabled());
        }
    }

    public function testAnAuthFailureIsNotDisabled(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 401, '{"error":"Unauthorized"}');
        try {
            $this->api($t)->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertFalse($e->isFeatureDisabled());
            self::assertSame(401, $e->status());
        }
    }

    public function testNoResponseIsUnreachable(): void
    {
        $t = (new FakeTransport())->down('edge-1');
        try {
            $this->api($t)->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertTrue($e->isUnreachable());
            self::assertStringContainsString('Failed to connect to Trident', $e->getMessage());
        }
    }

    public function testAThrowingTransportIsUnreachableNotAnUncaughtError(): void
    {
        $transport = new class () implements Transport {
            public function request(string $method, string $url, array $headers, ?string $body): array
            {
                throw new \RuntimeException('socket exploded');
            }
        };
        try {
            $this->api($transport)->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertTrue($e->isUnreachable());
            self::assertStringContainsString('socket exploded', $e->getMessage());
        }
    }

    public function testA429IsRetriedOnceAfterTheWaitItAsksFor(): void
    {
        $t = (new FakeTransport())
            ->then('edge-1', 429, '{"error":"Too Many Requests","retry_after_ms":20}')
            ->then('edge-1', 200, '{"entries":3}');
        $reply = $this->api($t)->call('GET', '/admin/stats');

        self::assertSame(3, $reply['data']['entries']);
        self::assertCount(2, $t->requests);
        self::assertSame([20], $this->slept);
    }

    public function testTheRetryWaitIsCapped(): void
    {
        $t = (new FakeTransport())
            ->then('edge-1', 429, '{"retry_after_ms":60000}')
            ->then('edge-1', 200, '{}');
        $this->api($t)->call('GET', '/admin/stats');
        self::assertSame([Api::RETRY_CAP_MS], $this->slept);
    }

    public function testA429IsRetriedOnlyOnce(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 429, '{"retry_after_ms":5}');
        try {
            $this->api($t)->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertSame(429, $e->status());
            self::assertCount(2, $t->requests);
        }
    }

    public function testA429WithoutAWaitIsNotRetried(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 429, 'slow down');
        try {
            $this->api($t)->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertCount(1, $t->requests);
            self::assertSame([], $this->slept);
        }
    }

    public function testTheTokenNeverAppearsInAnErrorReason(): void
    {
        $t = (new FakeTransport())->answer('edge-1', 500, '{"error":"boom"}');
        try {
            $this->api($t, 'super-secret-token')->call('GET', '/admin/stats');
            self::fail('expected ApiError');
        } catch (ApiError $e) {
            self::assertStringNotContainsString('super-secret-token', $e->reason());
            self::assertStringNotContainsString('super-secret-token', $e->getMessage());
        }
    }
}
