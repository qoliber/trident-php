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

class ErrorStatsResponse
{
    /**
     * @param array<int, int> $byStatus
     * @param array<array{url: string, status: int, time: string}> $recent
     */
    public function __construct(
        public readonly int $totalRequests = 0,
        public readonly int $totalErrors = 0,
        public readonly float $errorRate = 0.0,
        public readonly array $byStatus = [],
        public readonly array $recent = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalRequests: (int) ($data['total_requests'] ?? 0),
            totalErrors: (int) ($data['total_errors'] ?? 0),
            errorRate: (float) ($data['error_rate'] ?? 0.0),
            byStatus: $data['by_status'] ?? [],
            recent: $data['recent'] ?? []
        );
    }

    public function hasErrors(): bool
    {
        return $this->totalErrors > 0;
    }

    public function getErrorPercentage(): float
    {
        return $this->errorRate * 100;
    }

    public function getCountForStatus(int $status): int
    {
        return $this->byStatus[$status] ?? 0;
    }
}
