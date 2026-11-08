<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Qoliber\Trident\Cache\TagResolver;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\TridentFactory;
use Qoliber\Trident\TridentManager;

class TridentFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $manager = TridentFactory::create('http://localhost:9100', 'test-api-key');

        $this->assertInstanceOf(TridentManager::class, $manager);
        $this->assertInstanceOf(TridentClient::class, $manager->getClient());
    }

    public function testCreateWithoutApiKey(): void
    {
        $manager = TridentFactory::create('http://localhost:9100');

        $this->assertInstanceOf(TridentManager::class, $manager);
    }

    public function testCreateWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $manager = TridentFactory::create('http://localhost:9100', 'api-key', $logger);

        $this->assertInstanceOf(TridentManager::class, $manager);
    }

    public function testCreateFromEnv(): void
    {
        putenv('TRIDENT_ADMIN_URL=http://test-trident:9100');
        putenv('TRIDENT_ADMIN_KEY=env-api-key');

        try {
            $manager = TridentFactory::createFromEnv();

            $this->assertInstanceOf(TridentManager::class, $manager);
        } finally {
            putenv('TRIDENT_ADMIN_URL');
            putenv('TRIDENT_ADMIN_KEY');
        }
    }

    public function testCreateFromEnvWithDefaults(): void
    {
        putenv('TRIDENT_ADMIN_URL');
        putenv('TRIDENT_ADMIN_KEY');

        $manager = TridentFactory::createFromEnv();

        $this->assertInstanceOf(TridentManager::class, $manager);
    }

    public function testCreateWithClient(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $tagResolver = new TagResolver();

        $manager = TridentFactory::createWithClient(
            'http://localhost:9100',
            'api-key',
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger,
            $tagResolver
        );

        $this->assertInstanceOf(TridentManager::class, $manager);
        $this->assertSame($tagResolver, $manager->getTagResolver());
    }

    public function testCreateWithClientWithoutOptionalParams(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $manager = TridentFactory::createWithClient(
            'http://localhost:9100',
            null,
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(TridentManager::class, $manager);
    }

    public function testCreateClient(): void
    {
        $client = TridentFactory::createClient('http://localhost:9100', 'api-key');

        $this->assertInstanceOf(TridentClient::class, $client);
    }

    public function testCreateClientWithoutApiKey(): void
    {
        $client = TridentFactory::createClient('http://localhost:9100');

        $this->assertInstanceOf(TridentClient::class, $client);
    }

    public function testCreateClientWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = TridentFactory::createClient('http://localhost:9100', 'api-key', $logger);

        $this->assertInstanceOf(TridentClient::class, $client);
    }

    public function testCreateRefusesMetadataBeforeTerminalHandler(): void
    {
        $this->assertMetadataRefused('manager');
    }

    public function testCreateClientRefusesMetadataBeforeTerminalHandler(): void
    {
        $this->assertMetadataRefused('client');
    }

    public function testCreateFromEnvRefusesMetadataBeforeTerminalHandler(): void
    {
        $this->assertMetadataRefused('environment');
    }

    public function testDefaultClientsPreserveRequestAndGuardOptions(): void
    {
        $names = ['http_proxy', 'HTTP_PROXY', 'https_proxy', 'HTTPS_PROXY', 'ALL_PROXY', 'NO_PROXY'];
        $previous = [];
        foreach ($names as $name) {
            $previous[$name] = getenv($name);
            putenv($name . '=' . ($name === 'NO_PROXY' ? '' : 'http://127.0.0.1:9'));
        }
        try {
            foreach (['manager', 'client', 'environment'] as $factory) {
                $client = $this->factoryClient($factory, 'http://127.0.0.1:9100');
                $calls = [];
                $http = $this->replaceTerminalHandler($client, $calls);
                $client->purgeTags(['factory-tag']);

                $this->assertCount(1, $calls, $factory);
                [$request, $options] = $calls[0];
                $this->assertSame('POST', $request->getMethod());
                $this->assertSame('http://127.0.0.1:9100/admin/purge/tags', (string) $request->getUri());
                $this->assertSame('{"tags":["factory-tag"],"match_mode":"any"}', (string) $request->getBody());
                $this->assertSame('Bearer factory-api-key', $request->getHeaderLine('Authorization'));
                $this->assertSame('application/json', $request->getHeaderLine('Accept'));
                $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
                $this->assertGreaterThan(0, $options['timeout']);
                $this->assertLessThanOrEqual(10, $options['timeout']);
                $this->assertGreaterThan(0, $options['connect_timeout']);
                $this->assertLessThanOrEqual(5, $options['connect_timeout']);
                $this->assertEqualsWithDelta(5, $options['timeout'] - $options['connect_timeout'], 0.000000001,
                    'Both effective budgets must debit the same elapsed guard time');
                $this->assertFalse($options['http_errors']);
                $this->assertFalse($options['allow_redirects']);
                $this->assertSame([], $options['proxy'], 'Environment proxies must not receive admin requests');
                $this->assertSame('*', $options['curl'][CURLOPT_NOPROXY]);
                $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $options['curl'], 'Numeric hosts need no DNS lookup');
                $this->assertEquals(10, $http->getConfig('timeout'));
                $this->assertEquals(5, $http->getConfig('connect_timeout'));
            }
        } finally {
            foreach ($previous as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        }
    }

    public function testExplicitCustomClientIsPreservedWithoutAutomaticGuard(): void
    {
        $calls = 0;
        $http = new \GuzzleHttp\Client(['handler' => static function ($request, array $options) use (&$calls) {
            ++$calls;
            return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], '{"success":true}'));
        }]);
        $factory = new \GuzzleHttp\Psr7\HttpFactory();
        $manager = TridentFactory::createWithClient('http://169.254.169.254', null, $http, $factory, $factory);
        $property = new \ReflectionProperty(TridentClient::class, 'httpClient');
        $this->assertSame($http, $property->getValue($manager->getClient()));
        $manager->getClient()->health();
        $this->assertSame(1, $calls, 'Explicit PSR-18 transport policy remains the caller responsibility');
    }

    private function assertMetadataRefused(string $factory): void
    {
        $client = $this->factoryClient($factory, 'http://169.254.169.254');
        $calls = [];
        $this->replaceTerminalHandler($client, $calls);
        $error = null;
        try {
            $client->health();
        } catch (\Qoliber\Trident\Admin\ApiError $caught) {
            $error = $caught;
        }
        $this->assertCount(0, $calls, 'Default factory must refuse metadata before its terminal handler');
        $this->assertInstanceOf(\Qoliber\Trident\Admin\ApiError::class, $error);
        $this->assertStringContainsString('link-local / cloud metadata', $error->getMessage());
    }

    private function factoryClient(string $factory, string $url): TridentClient
    {
        if ($factory === 'client') {
            return TridentFactory::createClient($url, 'factory-api-key');
        }
        if ($factory === 'manager') {
            return TridentFactory::create($url, 'factory-api-key')->getClient();
        }
        $oldUrl = getenv('TRIDENT_ADMIN_URL');
        $oldKey = getenv('TRIDENT_ADMIN_KEY');
        putenv('TRIDENT_ADMIN_URL=' . $url);
        putenv('TRIDENT_ADMIN_KEY=factory-api-key');
        try {
            return TridentFactory::createFromEnv()->getClient();
        } finally {
            putenv($oldUrl === false ? 'TRIDENT_ADMIN_URL' : 'TRIDENT_ADMIN_URL=' . $oldUrl);
            putenv($oldKey === false ? 'TRIDENT_ADMIN_KEY' : 'TRIDENT_ADMIN_KEY=' . $oldKey);
        }
    }

    /** Replace only the terminal; retain the actual default middleware stack. */
    private function replaceTerminalHandler(TridentClient $client, array &$calls): \GuzzleHttp\Client
    {
        $property = new \ReflectionProperty(TridentClient::class, 'httpClient');
        $http = $property->getValue($client);
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $http);
        $stack = $http->getConfig('handler');
        $this->assertInstanceOf(\GuzzleHttp\HandlerStack::class, $stack);
        $stack->setHandler(static function ($request, array $options) use (&$calls) {
            $calls[] = [$request, $options];
            return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], '{"success":true,"purged":1}'));
        });
        return $http;
    }
}
