<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use GuzzleHttp\Psr7\Utils;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Events\EventStream;
use Qoliber\Trident\Http\TransportFactory;

/**
 * A bounded read of an instance's live event stream (`/admin/events`).
 *
 * The stream never ends; an admin request can hold it only briefly. The
 * response body goes to a sink the poller owns, so what arrived before the
 * overall timeout survives the timeout (a sink the client created would be
 * closed with the failed request), and {@see EventStream::parseChunk()} keeps
 * the complete events, dropping a cut-off tail.
 */
final class EventPoller
{
    public const MAX_EVENTS = 200;

    /**
     * @return array{status: int, error: ?string, events: list<array{type: string, id: ?string, data: array<string, mixed>}>}
     */
    public static function poll(TransportFactory $transports, Instance $instance, float $seconds): array
    {
        $sink = Utils::streamFor(fopen('php://temp', 'w+b') ?: '');
        $status = 0;
        $error = null;
        $client = $transports->guzzle($seconds);
        $headers = array_merge(Api::headers($instance, false), ['Accept' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
        try {
            $response = $client->request('GET', $instance->apiUrl . '/admin/events', ['headers' => $headers, 'sink' => $sink, 'timeout' => $seconds]);
            $status = $response->getStatusCode();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // The overall timeout is the normal end of a poll; the answer's
            // status (when there was one) says whether it was the stream.
            $status = $e->getResponse()?->getStatusCode() ?? ($sink->getSize() > 0 ? 200 : 0);
            if ($status === 0) {
                $error = 'no response — ' . $e->getMessage();
            }
        } catch (\Throwable $e) {
            $error = 'no response — ' . $e->getMessage();
        }
        if ($error === null && $status !== 200) {
            $error = sprintf('HTTP %d', $status);
        }
        $sink->rewind();
        $events = [];
        if ($error === null) {
            foreach (array_slice(EventStream::parseChunk($sink->getContents()), -self::MAX_EVENTS) as $event) {
                $events[] = ['type' => $event->getType(), 'id' => $event->getId(), 'data' => $event->getData()];
            }
        }

        return ['status' => $status, 'error' => $error, 'events' => $events];
    }
}
