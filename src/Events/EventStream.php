<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Events;

use Generator;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Qoliber\Trident\Exception\TridentException;

/**
 * Server-Sent Events (SSE) stream handler for Trident real-time events
 */
class EventStream
{
    private string $baseUrl;
    private ?string $apiKey;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;

    public function __construct(
        string $baseUrl,
        ?string $apiKey,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Stream request events
     *
     * @return Generator<TridentEvent>
     */
    public function requests(): Generator
    {
        return $this->stream('/admin/events/requests');
    }

    /**
     * Stream cache events (hits, misses, evictions)
     *
     * @return Generator<TridentEvent>
     */
    public function cache(): Generator
    {
        return $this->stream('/admin/events/cache');
    }

    /**
     * Stream backend events (status changes)
     *
     * @return Generator<TridentEvent>
     */
    public function backends(): Generator
    {
        return $this->stream('/admin/events/backends');
    }

    /**
     * Stream error events
     *
     * @return Generator<TridentEvent>
     */
    public function errors(): Generator
    {
        return $this->stream('/admin/events/errors');
    }

    /**
     * Stream launch mode events
     *
     * @return Generator<TridentEvent>
     */
    public function launch(): Generator
    {
        return $this->stream('/admin/launch/events');
    }

    /**
     * Stream generic events (reload notifications, etc.)
     *
     * @return Generator<TridentEvent>
     */
    public function events(): Generator
    {
        return $this->stream('/admin/events');
    }

    /**
     * Open an SSE stream and yield events
     *
     * @return Generator<TridentEvent>
     * @throws TridentException
     */
    private function stream(string $endpoint): Generator
    {
        $url = $this->baseUrl . $endpoint;

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache');

        if ($this->apiKey !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            throw new TridentException(
                'Failed to connect to Trident event stream: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new TridentException(
                sprintf('Trident event stream error (HTTP %d)', $statusCode),
                $statusCode
            );
        }

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;

            // Parse SSE format: "event: type\ndata: json\n\n"
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $message = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $event = self::parseEvent($message);
                if ($event !== null) {
                    yield $event;
                }
            }
        }
    }

    /**
     * Every complete event in a buffered burst of an SSE stream — for a
     * caller that reads the stream for a bounded time and parses what
     * arrived (a PHP admin page cannot hold the stream open). A trailing
     * partial event is dropped; `connected` handshakes and keepalives are
     * events like any other, filter them by type.
     *
     * @return list<TridentEvent>
     */
    public static function parseChunk(string $chunk): array
    {
        $events = [];
        $chunk = str_replace(["\r\n", "\r"], "\n", $chunk);
        $messages = explode("\n\n", $chunk);
        array_pop($messages); // not terminated by a blank line: incomplete
        foreach ($messages as $message) {
            $event = self::parseEvent($message);
            if ($event !== null) {
                $events[] = $event;
            }
        }
        return $events;
    }

    private static function parseEvent(string $message): ?TridentEvent
    {
        $eventType = 'message';
        $data = '';
        $id = null;

        foreach (explode("\n", $message) as $line) {
            if (str_starts_with($line, 'event:')) {
                $eventType = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            } elseif (str_starts_with($line, 'id:')) {
                $id = trim(substr($line, 3));
            }
        }

        if ($data === '') {
            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $data];
        }

        return new TridentEvent($eventType, $decoded, $id);
    }
}
