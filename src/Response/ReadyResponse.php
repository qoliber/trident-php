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

class ReadyResponse
{
    use CarriesRaw;

    /**
     * @param array<string, string> $backends
     */
    public function __construct(
        public readonly bool $ready,
        public readonly string $status,
        public readonly array $backends = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Trident returns {"ready": true/false, "backends_healthy": n, "backends_total": n}
        $ready = $data['ready'] ?? (($data['status'] ?? '') === 'ready');

        return (new self(
            ready: (bool) $ready,
            status: $ready ? 'ready' : ($data['status'] ?? 'not_ready'),
            backends: $data['backends'] ?? []
        ))->attachRaw($data);
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function isBackendHealthy(string $name): bool
    {
        return ($this->backends[$name] ?? '') === 'healthy';
    }

    public function getUnhealthyBackends(): array
    {
        return array_keys(array_filter($this->backends, fn($status) => $status !== 'healthy'));
    }
}
