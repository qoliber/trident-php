<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Transport over any PSR-18 client — for Composer-native platforms (Shopware,
 * Sylius, Magento with Guzzle). Timeouts are the client's own configuration:
 * give it short ones (a few seconds), because a delivery runs at the end of a
 * shop request.
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function request(string $method, string $url, array $headers, ?string $body): array
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            return ['status' => 0, 'body' => '', 'error' => $e->getMessage()];
        }
        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
            'error' => null,
        ];
    }
}
