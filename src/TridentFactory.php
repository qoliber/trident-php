<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Qoliber\Trident\Cache\TagResolver;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Http\TransportFactory;

/**
 * Factory for creating Trident instances with sensible defaults
 */
class TridentFactory
{
    /**
     * Create a TridentManager with Guzzle HTTP client
     */
    public static function create(
        string $adminUrl,
        ?string $apiKey = null,
        ?LoggerInterface $logger = null
    ): TridentManager {
        $httpClient = self::defaultHttpClient();

        $httpFactory = new HttpFactory();

        return self::createWithClient(
            $adminUrl,
            $apiKey,
            $httpClient,
            $httpFactory,
            $httpFactory,
            $logger
        );
    }

    /**
     * Create a TridentManager from environment variables
     */
    public static function createFromEnv(?LoggerInterface $logger = null): TridentManager
    {
        $adminUrl = getenv('TRIDENT_ADMIN_URL') ?: 'http://localhost:9100';
        $apiKey = getenv('TRIDENT_ADMIN_KEY') ?: null;

        return self::create($adminUrl, $apiKey ?: null, $logger);
    }

    /**
     * Create a TridentManager with custom HTTP client
     */
    public static function createWithClient(
        string $adminUrl,
        ?string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?TagResolver $tagResolver = null
    ): TridentManager {
        $client = new TridentClient(
            $adminUrl,
            $apiKey,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger
        );

        return new TridentManager($client, $tagResolver);
    }

    /**
     * Create just the TridentClient without the Manager facade
     */
    public static function createClient(
        string $adminUrl,
        ?string $apiKey = null,
        ?LoggerInterface $logger = null
    ): TridentClient {
        $httpClient = self::defaultHttpClient();

        $httpFactory = new HttpFactory();

        return new TridentClient(
            $adminUrl,
            $apiKey,
            $httpClient,
            $httpFactory,
            $httpFactory,
            $logger
        );
    }

    private static function defaultHttpClient(): ClientInterface
    {
        if (!\extension_loaded('curl') || !\function_exists('curl_init')
            || !\function_exists('curl_exec') || !\defined('CURLOPT_RESOLVE')
            || !\defined('CURLOPT_NOPROXY')) {
            throw new \LogicException('The default TridentFactory client requires ext-curl for guarded connections; use createWithClient() for an explicitly configured PSR-18 client');
        }

        return (new TransportFactory(timeout: 10.0, connectTimeout: 5.0))->client();
    }
}
