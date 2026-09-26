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

class LatencyStatsResponse
{
    use CarriesRaw;

    /**
     * @param array{avg_ms?: float, p50_ms?: float, p75_ms?: float, p90_ms?: float, p95_ms?: float, p99_ms?: float, min_ms?: float, max_ms?: float}|null $latency
     */
    public function __construct(
        public readonly ?array $latency = null,
        public readonly int $sampleCount = 0,
        public readonly int $windowSecs = 0
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            latency: $data['latency'] ?? null,
            // 1.8 engines report the sample count inside `latency`.
            sampleCount: (int) ($data['sample_count'] ?? $data['latency']['count'] ?? 0),
            windowSecs: (int) ($data['window_secs'] ?? 0)
        ))->attachRaw($data);
    }

    public function getAvgMs(): float
    {
        return $this->latency['avg_ms'] ?? 0.0;
    }

    public function getP50Ms(): float
    {
        return $this->latency['p50_ms'] ?? 0.0;
    }

    public function getP75Ms(): float
    {
        return $this->latency['p75_ms'] ?? 0.0;
    }

    public function getP90Ms(): float
    {
        return $this->latency['p90_ms'] ?? 0.0;
    }

    public function getP95Ms(): float
    {
        return $this->latency['p95_ms'] ?? 0.0;
    }

    public function getP99Ms(): float
    {
        return $this->latency['p99_ms'] ?? 0.0;
    }

    public function getMinMs(): float
    {
        return $this->latency['min_ms'] ?? 0.0;
    }

    public function getMaxMs(): float
    {
        return $this->latency['max_ms'] ?? 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'avg_ms' => $this->getAvgMs(),
            'p50_ms' => $this->getP50Ms(),
            'p75_ms' => $this->getP75Ms(),
            'p90_ms' => $this->getP90Ms(),
            'p95_ms' => $this->getP95Ms(),
            'p99_ms' => $this->getP99Ms(),
            'min_ms' => $this->getMinMs(),
            'max_ms' => $this->getMaxMs(),
            'sample_count' => $this->sampleCount,
            'window_secs' => $this->windowSecs,
        ];
    }
}
