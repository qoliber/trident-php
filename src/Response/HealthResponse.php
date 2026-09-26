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
        return new self(
            healthy: ($data['status'] ?? '') === 'healthy' || ($data['healthy'] ?? false),
            status: $data['status'] ?? 'unknown',
            version: $data['version'] ?? null,
            uptime: isset($data['uptime']) ? (int) $data['uptime'] : null,
            backends: $data['backends'] ?? null
        );
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
