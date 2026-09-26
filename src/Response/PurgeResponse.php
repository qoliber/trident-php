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

use Qoliber\Trident\Delivery\Acknowledgement;

class PurgeResponse
{
    use CarriesRaw;

    /**
     * @param bool        $success      Kept for compatibility: true for any purge body,
     *                                  even one Trident did not act on — use
     *                                  isAcknowledged() to decide whether a purge happened.
     * @param bool        $acknowledged Trident took it: HTTP 200 and the engine's
     *                                  acknowledgement schema (see Delivery\Acknowledgement).
     * @param string|null $state        1.8+: applied|recorded|refused.
     * @param string|null $failure      Why it is not an acknowledgement.
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $purgedCount,
        public readonly ?string $mode = null,
        public readonly ?int $queuedRefresh = null,
        public readonly ?int $bytesFreed = null,
        public readonly ?string $message = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $details = null,
        public readonly bool $acknowledged = false,
        public readonly ?string $state = null,
        public readonly ?string $failure = null
    ) {
    }

    /**
     * @param array<string, mixed> $data       Decoded response body.
     * @param int|null             $statusCode HTTP status. The client only returns bodies
     *                                         of successful responses, so null means 200.
     */
    public static function fromArray(array $data, ?int $statusCode = null): self
    {
        // Handle both purge response and clear response formats
        $purged = (int) ($data['purged'] ?? $data['entries_removed'] ?? $data['purged_count'] ?? $data['count'] ?? 0);
        $success = isset($data['cleared']) ? (bool) $data['cleared'] : ($purged >= 0);
        $status = $statusCode ?? 200;
        $body = (string) json_encode($data);
        $failure = array_key_exists('cleared', $data)
            ? Acknowledgement::clearFailure($status, $body)
            : Acknowledgement::purgeFailure($status, $body);

        return (new self(
            success: $success,
            purgedCount: $purged,
            mode: $data['mode'] ?? null,
            queuedRefresh: isset($data['queued_refresh']) ? (int) $data['queued_refresh'] : null,
            bytesFreed: isset($data['bytes_freed']) ? (int) $data['bytes_freed'] : null,
            message: $data['message'] ?? null,
            details: $data['details'] ?? null,
            acknowledged: $failure === null,
            state: isset($data['state']) && is_string($data['state']) ? $data['state'] : null,
            failure: $failure
        ))->attachRaw($data);
    }

    /**
     * Whether Trident acknowledged the invalidation. Only this may be used to
     * decide that a purge happened (and, for example, to drop it from a retry
     * queue): a 200 with an error object, a `state: refused`, or a body of
     * another schema is not a purge.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPurgedCount(): int
    {
        return $this->purgedCount;
    }

    public function isSoftPurge(): bool
    {
        return $this->mode === 'soft';
    }
}
