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

class RefreshQueueResponse
{
    public function __construct(
        public readonly int $pending,
        public readonly int $queueCapacity,
        public readonly int $workers,
        public readonly int $queued,
        public readonly int $completed,
        public readonly int $failed,
        public readonly int $duplicates,
        public readonly int $queueFull
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $stats = $data['stats'] ?? [];

        return new self(
            pending: (int) ($data['pending'] ?? 0),
            queueCapacity: (int) ($data['queue_capacity'] ?? 0),
            workers: (int) ($data['workers'] ?? 0),
            queued: (int) ($stats['queued'] ?? 0),
            completed: (int) ($stats['completed'] ?? 0),
            failed: (int) ($stats['failed'] ?? 0),
            duplicates: (int) ($stats['duplicates'] ?? 0),
            queueFull: (int) ($stats['queue_full'] ?? 0)
        );
    }

    public function getSuccessRate(): float
    {
        $total = $this->completed + $this->failed;
        if ($total === 0) {
            return 100.0;
        }
        return round(($this->completed / $total) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pending' => $this->pending,
            'queue_capacity' => $this->queueCapacity,
            'workers' => $this->workers,
            'stats' => [
                'queued' => $this->queued,
                'completed' => $this->completed,
                'failed' => $this->failed,
                'duplicates' => $this->duplicates,
                'queue_full' => $this->queueFull,
            ],
        ];
    }
}
