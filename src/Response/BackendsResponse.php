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

class BackendsResponse
{
    use CarriesRaw;

    /**
     * @param array<array{name: string, host: string, port: int, status: string, healthy: bool, total_requests: int, total_errors: int, active_connections: int, avg_response_ms: float}> $backends
     */
    public function __construct(
        public readonly int $total,
        public readonly int $healthy,
        public readonly int $unhealthy,
        public readonly array $backends
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            total: (int) ($data['total'] ?? 0),
            healthy: (int) ($data['healthy'] ?? 0),
            unhealthy: (int) ($data['unhealthy'] ?? 0),
            backends: $data['backends'] ?? []
        ))->attachRaw($data);
    }

    public function isAllHealthy(): bool
    {
        return $this->unhealthy === 0 && $this->total > 0;
    }

    public function getBackend(string $name): ?array
    {
        foreach ($this->backends as $backend) {
            if ($backend['name'] === $name) {
                return $backend;
            }
        }
        return null;
    }

    public function isBackendHealthy(string $name): bool
    {
        $backend = $this->getBackend($name);
        return $backend !== null && ($backend['healthy'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'healthy' => $this->healthy,
            'unhealthy' => $this->unhealthy,
            'backends' => $this->backends,
        ];
    }
}
