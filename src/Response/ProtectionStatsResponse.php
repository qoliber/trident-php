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
    /**
     * @param array<BackendProtectionStats> $backends
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly int $totalTripped,
        public readonly int $activeTrips,
        public readonly array $backends
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $backends = array_map(
            fn(array $item) => BackendProtectionStats::fromArray($item),
            (array) ($data['backends'] ?? [])
        );

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            totalTripped: (int) ($data['total_tripped'] ?? 0),
            activeTrips: (int) ($data['active_trips'] ?? 0),
            backends: $backends
        );
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
    public function __construct(
        public readonly string $name,
        public readonly bool $tripped,
        public readonly int $tripCount,
        public readonly int $errorCount,
        public readonly float $errorRate,
        public readonly ?string $lastTrip,
        public readonly ?int $cooldownRemaining
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            tripped: (bool) ($data['tripped'] ?? false),
            tripCount: (int) ($data['trip_count'] ?? 0),
            errorCount: (int) ($data['error_count'] ?? 0),
            errorRate: (float) ($data['error_rate'] ?? 0.0),
            lastTrip: isset($data['last_trip']) ? (string) $data['last_trip'] : null,
            cooldownRemaining: isset($data['cooldown_remaining']) ? (int) $data['cooldown_remaining'] : null
        );
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
