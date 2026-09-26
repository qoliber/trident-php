<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Testing;

use Qoliber\Trident\Delivery\Transport;

/**
 * A scripted Trident admin API: per host, a queue of answers, else the host's
 * default, else a 1.8 purge acknowledgement. Records every request. See
 * InMemoryOutboxStore for why test doubles live in `src/`.
 *
 * @internal Not for production use.
 */
final class FakeTransport implements Transport
{
    public const ACK = '{"purged":1,"mode":"soft","state":"applied","barrier":"freshness","queued_refresh":1}';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @var array<string, array{status: int, body: string, error: ?string}> */
    private array $default = [];

    /** @var array<string, list<array{status: int, body: string, error: ?string}>> */
    private array $queue = [];

    /** Every request to `$host` gets this answer. */
    public function answer(string $host, int $status, string $body = self::ACK, ?string $error = null): self
    {
        $this->default[$host] = ['status' => $status, 'body' => $body, 'error' => $error];
        return $this;
    }

    /** The next request to `$host` gets this answer (queued). */
    public function then(string $host, int $status, string $body = self::ACK): self
    {
        $this->queue[$host][] = ['status' => $status, 'body' => $body, 'error' => null];
        return $this;
    }

    /** `$host` does not answer at all. */
    public function down(string $host): self
    {
        return $this->answer($host, 0, '', 'cURL error 7: Failed to connect');
    }

    public function request(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (!empty($this->queue[$host])) {
            return array_shift($this->queue[$host]);
        }
        return $this->default[$host] ?? ['status' => 200, 'body' => self::ACK, 'error' => null];
    }

    /**
     * @return list<list<string>> The tags of each purge request sent to `$host`.
     */
    public function purgedTags(string $host): array
    {
        $out = [];
        foreach ($this->requests as $r) {
            if (parse_url($r['url'], PHP_URL_HOST) === $host && str_ends_with($r['url'], '/admin/purge/tags')) {
                $decoded = json_decode((string) $r['body'], true);
                $out[] = is_array($decoded) && is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [];
            }
        }
        return $out;
    }
}
