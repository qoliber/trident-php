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

class LaunchResponse
{
    use CarriesRaw;

    public function __construct(
        public readonly bool $success,
        public readonly ?string $launchId = null,
        public readonly ?string $status = null,
        public readonly ?string $startedAt = null,
        public readonly ?int $urlsTotal = null,
        public readonly ?int $urlsCompleted = null,
        public readonly ?bool $maintenanceActive = null,
        public readonly ?string $message = null,
        public readonly ?string $reason = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        return (new self(
            // The engine says so directly (`success`); older shapes only implied it.
            success: isset($data['success'])
                ? (bool) $data['success']
                : (isset($data['launch_id']) || in_array($status, ['warming', 'ready', 'completed', 'aborted'], true)),
            launchId: $data['launch_id'] ?? null,
            status: $status,
            startedAt: $data['started_at'] ?? null,
            urlsTotal: isset($data['urls_total']) ? (int) $data['urls_total']
                : (isset($data['total_urls']) ? (int) $data['total_urls'] : null),
            urlsCompleted: isset($data['urls_completed']) ? (int) $data['urls_completed']
                : (isset($data['urls_warmed']) ? (int) $data['urls_warmed'] : null),
            maintenanceActive: $data['maintenance_active'] ?? null,
            message: $data['message'] ?? null,
            reason: $data['reason'] ?? null
        ))->attachRaw($data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
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

    public function isAborted(): bool
    {
        return $this->status === 'aborted';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isMaintenanceActive(): bool
    {
        return $this->maintenanceActive === true;
    }
}
