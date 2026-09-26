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

class DiscoveryListResponse
{
    /**
     * @param array<DiscoveryBackend> $backends
     */
    public function __construct(
        public readonly array $backends,
        public readonly int $total
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $backends = array_map(
            fn(array $item) => DiscoveryBackend::fromArray($item),
            (array) ($data['backends'] ?? [])
        );

        return new self(
            backends: $backends,
            total: (int) ($data['total'] ?? count($backends))
        );
    }

    /**
     * @return array<DiscoveryBackend>
     */
    public function getBackends(): array
    {
        return $this->backends;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function isEmpty(): bool
    {
        return empty($this->backends);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'backends' => array_map(fn(DiscoveryBackend $b) => $b->toArray(), $this->backends),
            'total' => $this->total,
        ];
    }
}

class DiscoveryBackend
{
    /**
     * @param array<string> $resolvedIps
     */
    public function __construct(
        public readonly string $name,
        public readonly string $hostname,
        public readonly array $resolvedIps,
        public readonly int $healthyCount,
        public readonly int $unhealthyCount,
        public readonly ?string $lastResolved
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            hostname: (string) ($data['hostname'] ?? $data['host'] ?? ''),
            resolvedIps: (array) ($data['resolved_ips'] ?? $data['ips'] ?? []),
            healthyCount: (int) ($data['healthy_count'] ?? $data['healthy'] ?? 0),
            unhealthyCount: (int) ($data['unhealthy_count'] ?? $data['unhealthy'] ?? 0),
            lastResolved: isset($data['last_resolved']) ? (string) $data['last_resolved'] : null
        );
    }

    public function getTotalIps(): int
    {
        return count($this->resolvedIps);
    }

    public function isFullyHealthy(): bool
    {
        return $this->unhealthyCount === 0 && $this->healthyCount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'resolved_ips' => $this->resolvedIps,
            'healthy_count' => $this->healthyCount,
            'unhealthy_count' => $this->unhealthyCount,
            'last_resolved' => $this->lastResolved,
        ];
    }
}
