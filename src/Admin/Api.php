<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\Transport;

/**
 * The one request path to a Trident admin API: authentication, JSON in and
 * out, error mapping, and a single bounded retry when the admin API's rate
 * limiter answers 429.
 *
 * Every admin call in the library goes through here — the typed
 * {@see \Qoliber\Trident\Client\TridentClient} and the purge delivery
 * ({@see \Qoliber\Trident\Delivery\PurgeClient} uses {@see self::headers()}) —
 * so there is one place that decides how a request is authenticated and what
 * an error looks like.
 */
final class Api
{
    /** Longest wait honoured from a 429's `retry_after_ms`. */
    public const RETRY_CAP_MS = 1000;

    private LoggerInterface $logger;

    /** @var \Closure(int): void */
    private \Closure $sleep;

    /**
     * @param (\Closure(int): void)|null $sleep Sleeps for the given milliseconds (injectable for tests).
     */
    public function __construct(
        private readonly Instance $instance,
        private readonly Transport $transport,
        ?LoggerInterface $logger = null,
        ?\Closure $sleep = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    public function instance(): Instance
    {
        return $this->instance;
    }

    /**
     * Request headers for an instance: the bearer token (only when one is set)
     * and JSON negotiation.
     *
     * @return array<string, string>
     */
    public static function headers(Instance $instance, bool $jsonBody = true): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($instance->apiToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $instance->apiToken;
        }
        if ($jsonBody) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

    /**
     * Call an endpoint and return its decoded JSON body.
     *
     * An empty or non-JSON 2xx body decodes to `['success' => true,
     * 'status_code' => N]` — several engine endpoints answer that way.
     *
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null  $body
     * @return array{status: int, data: array<string, mixed>}
     * @throws ApiError No response, or HTTP status >= 400.
     */
    public function call(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = rtrim($this->instance->apiUrl, '/') . $path;
        $query = array_filter($query, static fn ($v): bool => $v !== null);
        if ($query !== []) {
            $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }
        $payload = null;
        if ($body !== null) {
            // An empty body is an empty OBJECT: json_encode([]) is "[]", which
            // the engine's struct deserializers reject.
            $payload = $body === [] ? '{}' : json_encode($body);
            if ($payload === false) {
                throw new ApiError('Failed to encode request body as JSON', 0);
            }
        }

        $response = $this->send($method, $url, $payload);
        if ($response['status'] === 429) {
            $wait = self::retryAfterMs($response['body']);
            if ($wait !== null) {
                ($this->sleep)($wait);
                $response = $this->send($method, $url, $payload);
            }
        }

        if ($response['status'] === 0) {
            throw new ApiError('Failed to connect to Trident: ' . (string) $response['error'], 0);
        }
        if ($response['status'] >= 400) {
            throw ApiError::fromResponse($response['status'], $response['body']);
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            $data = ['success' => true, 'status_code' => $response['status']];
        }
        return ['status' => $response['status'], 'data' => $data];
    }

    /**
     * @return array{status: int, body: string, error: string|null}
     */
    private function send(string $method, string $url, ?string $payload): array
    {
        $this->logger->debug('Trident API request', ['method' => $method, 'url' => $url]);
        try {
            $response = $this->transport->request($method, $url, self::headers($this->instance, $payload !== null), $payload);
        } catch (\Throwable $e) {
            // A transport that throws instead of reporting status 0 (a PSR-18
            // client raising a non-PSR exception) is still "no response".
            $response = ['status' => 0, 'body' => '', 'error' => $e->getMessage()];
        }
        $this->logger->debug('Trident API response', ['status' => $response['status']]);
        if ($response['status'] === 0) {
            $this->logger->error('Trident API request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $response['error'],
            ]);
        }
        return $response;
    }

    /**
     * The wait a 429 asks for, capped; null when it names none (then the 429
     * is returned as is — guessing a wait would just add latency).
     */
    private static function retryAfterMs(string $body): ?int
    {
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['retry_after_ms']) || !is_numeric($data['retry_after_ms'])) {
            return null;
        }
        return max(0, min(self::RETRY_CAP_MS, (int) $data['retry_after_ms']));
    }
}
