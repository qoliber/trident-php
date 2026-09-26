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

class DiscoveryDetailResponse
{
    use CarriesRaw;

    /**
     * @param array<DiscoveryIpStatus> $ips
     */
    public function __construct(
        public readonly string $name,
        public readonly string $hostname,
        public readonly int $port,
        public readonly array $ips,
        public readonly int $refreshIntervalSecs,
        public readonly ?string $lastResolved,
        public readonly ?string $nextRefresh
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $ips = array_map(
            fn(array $item) => DiscoveryIpStatus::fromArray($item),
            (array) ($data['ips'] ?? [])
        );

        return (new self(
            name: (string) ($data['name'] ?? ''),
            hostname: (string) ($data['hostname'] ?? $data['host'] ?? ''),
            port: (int) ($data['port'] ?? 80),
            ips: $ips,
            refreshIntervalSecs: (int) ($data['refresh_interval_secs'] ?? $data['refresh_interval'] ?? 60),
            lastResolved: isset($data['last_resolved']) ? (string) $data['last_resolved'] : null,
            nextRefresh: isset($data['next_refresh']) ? (string) $data['next_refresh'] : null
        ))->attachRaw($data);
    }

    /**
     * @return array<DiscoveryIpStatus>
     */
    public function getIps(): array
    {
        return $this->ips;
    }

    /**
     * @return array<DiscoveryIpStatus>
     */
    public function getHealthyIps(): array
    {
        return array_filter($this->ips, fn(DiscoveryIpStatus $ip) => $ip->isHealthy());
    }

    /**
     * @return array<DiscoveryIpStatus>
     */
    public function getUnhealthyIps(): array
    {
        return array_filter($this->ips, fn(DiscoveryIpStatus $ip) => !$ip->isHealthy());
    }

    public function getHealthyCount(): int
    {
        return count($this->getHealthyIps());
    }

    public function getUnhealthyCount(): int
    {
        return count($this->getUnhealthyIps());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'port' => $this->port,
            'ips' => array_map(fn(DiscoveryIpStatus $ip) => $ip->toArray(), $this->ips),
            'refresh_interval_secs' => $this->refreshIntervalSecs,
            'last_resolved' => $this->lastResolved,
            'next_refresh' => $this->nextRefresh,
        ];
    }
}

class DiscoveryIpStatus
{
    use CarriesRaw;

    public function __construct(
        public readonly string $ip,
        public readonly bool $healthy,
        public readonly int $requests,
        public readonly int $errors,
        public readonly ?float $avgResponseMs,
        public readonly ?string $lastError
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            ip: (string) ($data['ip'] ?? $data['address'] ?? ''),
            healthy: (bool) ($data['healthy'] ?? true),
            requests: (int) ($data['requests'] ?? 0),
            errors: (int) ($data['errors'] ?? 0),
            avgResponseMs: isset($data['avg_response_ms']) ? (float) $data['avg_response_ms'] : null,
            lastError: isset($data['last_error']) ? (string) $data['last_error'] : null
        ))->attachRaw($data);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function getErrorRate(): float
    {
        if ($this->requests === 0) {
            return 0.0;
        }

        return ($this->errors / $this->requests) * 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'healthy' => $this->healthy,
            'requests' => $this->requests,
            'errors' => $this->errors,
            'avg_response_ms' => $this->avgResponseMs,
            'last_error' => $this->lastError,
        ];
    }
}
