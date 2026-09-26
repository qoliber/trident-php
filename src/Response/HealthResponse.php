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

class HealthResponse
{
    use CarriesRaw;

    public function __construct(
        public readonly bool $healthy,
        public readonly string $status,
        public readonly ?string $version = null,
        public readonly ?int $uptime = null,
        public readonly ?array $backends = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            healthy: ($data['status'] ?? '') === 'healthy' || ($data['healthy'] ?? false),
            status: $data['status'] ?? 'unknown',
            version: $data['version'] ?? null,
            // The engine sends `uptime_seconds`; `uptime` is the pre-1.8 name.
            uptime: isset($data['uptime']) ? (int) $data['uptime']
                : (isset($data['uptime_seconds']) ? (int) $data['uptime_seconds'] : null),
            backends: $data['backends'] ?? null
        ))->attachRaw($data);
    }

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getUptime(): ?int
    {
        return $this->uptime;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBackends(): ?array
    {
        return $this->backends;
    }
}
