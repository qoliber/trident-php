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

class ProtectionStatsResponse
{
    use CarriesRaw;

    /**
     * @param array<BackendProtectionStats> $backends
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly int $totalTripped,
        public readonly int $activeTrips,
        public readonly array $backends,
        public readonly int $totalAcquired = 0,
        public readonly int $totalTimeouts = 0,
        public readonly int $totalQueueFull = 0,
        public readonly int $totalStaleServed = 0
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // The engine keys `backends` by name and the rows carry no `name`:
        // keep the keys (the 1.4.1 shape) and fill `name` from them.
        $backends = [];
        foreach ((array) ($data['backends'] ?? []) as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!isset($item['name']) && is_string($key)) {
                $item['name'] = $key;
            }
            $backends[$key] = BackendProtectionStats::fromArray($item);
        }

        return (new self(
            // 1.8 engines send `protection_enabled`.
            enabled: (bool) ($data['enabled'] ?? $data['protection_enabled'] ?? false),
            totalTripped: (int) ($data['total_tripped'] ?? 0),
            activeTrips: (int) ($data['active_trips'] ?? 0),
            backends: $backends,
            totalAcquired: (int) ($data['total_acquired'] ?? 0),
            totalTimeouts: (int) ($data['total_timeouts'] ?? 0),
            totalQueueFull: (int) ($data['total_queue_full'] ?? 0),
            totalStaleServed: (int) ($data['total_stale_served'] ?? 0)
        ))->attachRaw($data);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTotalTripped(): int
    {
        return $this->totalTripped;
    }

    public function getActiveTrips(): int
    {
        return $this->activeTrips;
    }

    /**
     * @return array<BackendProtectionStats>
     */
    public function getBackends(): array
    {
        return $this->backends;
    }

    public function hasActiveTrips(): bool
    {
        return $this->activeTrips > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'total_tripped' => $this->totalTripped,
            'active_trips' => $this->activeTrips,
            'backends' => array_map(fn(BackendProtectionStats $b) => $b->toArray(), $this->backends),
        ];
    }
}

class BackendProtectionStats
{
    use CarriesRaw;

    public function __construct(
        public readonly string $name,
        public readonly bool $tripped,
        public readonly int $tripCount,
        public readonly int $errorCount,
        public readonly float $errorRate,
        public readonly ?string $lastTrip,
        public readonly ?int $cooldownRemaining,
        public readonly int $acquiredTotal = 0,
        public readonly int $timeoutsTotal = 0,
        public readonly int $queueFullTotal = 0,
        public readonly int $staleServedTotal = 0,
        public readonly int $queueLength = 0,
        public readonly int $activeConnections = 0,
        public readonly float $avgQueueWaitMs = 0.0,
        public readonly float $p99QueueWaitMs = 0.0
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            name: (string) ($data['name'] ?? ''),
            tripped: (bool) ($data['tripped'] ?? false),
            tripCount: (int) ($data['trip_count'] ?? 0),
            errorCount: (int) ($data['error_count'] ?? 0),
            errorRate: (float) ($data['error_rate'] ?? 0.0),
            lastTrip: isset($data['last_trip']) ? (string) $data['last_trip'] : null,
            cooldownRemaining: isset($data['cooldown_remaining']) ? (int) $data['cooldown_remaining'] : null,
            acquiredTotal: (int) ($data['acquired_total'] ?? 0),
            timeoutsTotal: (int) ($data['timeouts_total'] ?? 0),
            queueFullTotal: (int) ($data['queue_full_total'] ?? 0),
            staleServedTotal: (int) ($data['stale_served_total'] ?? 0),
            queueLength: (int) ($data['queue_length'] ?? 0),
            activeConnections: (int) ($data['active_connections'] ?? 0),
            avgQueueWaitMs: (float) ($data['avg_queue_wait_ms'] ?? 0.0),
            p99QueueWaitMs: (float) ($data['p99_queue_wait_ms'] ?? 0.0)
        ))->attachRaw($data);
    }

    public function isTripped(): bool
    {
        return $this->tripped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tripped' => $this->tripped,
            'trip_count' => $this->tripCount,
            'error_count' => $this->errorCount,
            'error_rate' => $this->errorRate,
            'last_trip' => $this->lastTrip,
            'cooldown_remaining' => $this->cooldownRemaining,
        ];
    }
}
