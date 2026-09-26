<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Response;

class BackendDetailResponse
{
    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $latency
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $healthy,
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $address = null,
        public readonly ?string $status = null,
        public readonly int $weight = 1,
        public readonly int $maxConnections = 100,
        public readonly bool $tls = false,
        public readonly array $stats = [],
        public readonly array $latency = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            healthy: $data['healthy'] ?? false,
            host: $data['host'] ?? '',
            port: (int) ($data['port'] ?? 80),
            address: $data['address'] ?? null,
            status: $data['status'] ?? null,
            weight: (int) ($data['weight'] ?? 1),
            maxConnections: (int) ($data['max_connections'] ?? 100),
            tls: $data['tls'] ?? false,
            stats: $data['stats'] ?? [],
            latency: $data['latency'] ?? []
        );
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function getUrl(): string
    {
        $scheme = $this->tls ? 'https' : 'http';
        return sprintf('%s://%s:%d', $scheme, $this->host, $this->port);
    }

    public function getRequestCount(): int
    {
        return (int) ($this->stats['total_requests'] ?? $this->stats['requests'] ?? 0);
    }

    public function getErrorCount(): int
    {
        return (int) ($this->stats['failed_requests'] ?? $this->stats['errors'] ?? 0);
    }

    public function getErrorRate(): float
    {
        return (float) ($this->stats['error_rate'] ?? 0.0);
    }

    public function getActiveConnections(): int
    {
        return (int) ($this->stats['active_connections'] ?? 0);
    }

    public function getAvgLatencyMs(): float
    {
        return (float) ($this->latency['avg_ms'] ?? 0.0);
    }

    public function getP50LatencyMs(): float
    {
        return (float) ($this->latency['p50_ms'] ?? 0.0);
    }

    public function getP95LatencyMs(): float
    {
        return (float) ($this->latency['p95_ms'] ?? 0.0);
    }

    public function getP99LatencyMs(): float
    {
        return (float) ($this->latency['p99_ms'] ?? 0.0);
    }
}
