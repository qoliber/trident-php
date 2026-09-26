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

class ConnectionsResponse
{
    use CarriesRaw;

    /**
     * @param array<BackendConnections> $backends
     */
    public function __construct(
        public readonly int $totalActive,
        public readonly int $totalIdle,
        public readonly array $backends
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $backends = array_map(
            fn(array $item) => BackendConnections::fromArray($item),
            (array) ($data['backends'] ?? [])
        );

        return (new self(
            totalActive: (int) ($data['total_active'] ?? 0),
            totalIdle: (int) ($data['total_idle'] ?? 0),
            backends: $backends
        ))->attachRaw($data);
    }

    public function getTotalActive(): int
    {
        return $this->totalActive;
    }

    public function getTotalIdle(): int
    {
        return $this->totalIdle;
    }

    public function getTotalConnections(): int
    {
        return $this->totalActive + $this->totalIdle;
    }

    /**
     * @return array<BackendConnections>
     */
    public function getBackends(): array
    {
        return $this->backends;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_active' => $this->totalActive,
            'total_idle' => $this->totalIdle,
            'backends' => array_map(fn(BackendConnections $b) => $b->toArray(), $this->backends),
        ];
    }
}

class BackendConnections
{
    use CarriesRaw;

    public function __construct(
        public readonly string $name,
        public readonly int $active,
        public readonly int $idle,
        public readonly int $maxConnections,
        public readonly int $waitingRequests,
        public readonly ?bool $healthy = null,
        public readonly int $totalCreated = 0,
        public readonly int $totalReused = 0
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            name: (string) ($data['name'] ?? ''),
            active: (int) ($data['active'] ?? 0),
            idle: (int) ($data['idle'] ?? 0),
            maxConnections: (int) ($data['max_connections'] ?? $data['max'] ?? 0),
            // 1.8 engines call it `queued`.
            waitingRequests: (int) ($data['waiting_requests'] ?? $data['waiting'] ?? $data['queued'] ?? 0),
            healthy: isset($data['healthy']) ? (bool) $data['healthy'] : null,
            totalCreated: (int) ($data['total_created'] ?? 0),
            totalReused: (int) ($data['total_reused'] ?? 0)
        ))->attachRaw($data);
    }

    public function getTotalConnections(): int
    {
        return $this->active + $this->idle;
    }

    public function getUtilizationPercent(): float
    {
        if ($this->maxConnections === 0) {
            return 0.0;
        }

        return ($this->active / $this->maxConnections) * 100;
    }

    public function hasWaitingRequests(): bool
    {
        return $this->waitingRequests > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'active' => $this->active,
            'idle' => $this->idle,
            'max_connections' => $this->maxConnections,
            'waiting_requests' => $this->waitingRequests,
        ];
    }
}
