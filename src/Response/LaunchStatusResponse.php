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

class LaunchStatusResponse
{
    /**
     * @param array{total: int, completed: int, failed: int, pending: int, percent: int}|null $progress
     */
    public function __construct(
        public readonly string $launchId,
        public readonly string $status,
        public readonly ?array $progress = null,
        public readonly ?string $currentUrl = null,
        public readonly bool $maintenanceActive = false,
        public readonly bool $canComplete = false,
        public readonly bool $canAbort = true
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            launchId: $data['launch_id'] ?? '',
            status: $data['status'] ?? 'unknown',
            progress: $data['progress'] ?? null,
            currentUrl: $data['current_url'] ?? null,
            maintenanceActive: $data['maintenance_active'] ?? false,
            canComplete: $data['can_complete'] ?? false,
            canAbort: $data['can_abort'] ?? true
        );
    }

    public function isWarming(): bool
    {
        return $this->status === 'warming';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getProgressPercent(): int
    {
        return $this->progress['percent'] ?? 0;
    }

    public function getCompletedCount(): int
    {
        return $this->progress['completed'] ?? 0;
    }

    public function getTotalCount(): int
    {
        return $this->progress['total'] ?? 0;
    }

    public function getFailedCount(): int
    {
        return $this->progress['failed'] ?? 0;
    }

    public function getPendingCount(): int
    {
        return $this->progress['pending'] ?? 0;
    }
}
