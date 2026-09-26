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
}
