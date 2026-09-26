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

/**
 * The answer to a full cache clear (`POST /admin/cache/clear`, 1.5.0).
 *
 * The engine answers a clear with its own schema — `cleared`,
 * `entries_removed`, `bytes_freed` — not a purge's `purged`/`mode`: a clear
 * has no soft/hard mode. purgeAll() still returns a PurgeResponse for
 * compatibility; clearCache() returns this.
 */
final class ClearResponse
{
    use CarriesRaw;

    public function __construct(
        public readonly bool $cleared,
        public readonly int $entriesRemoved,
        public readonly int $bytesFreed,
        public readonly ?string $failure = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?int $statusCode = null): self
    {
        return (new self(
            cleared: ($data['cleared'] ?? null) === true,
            entriesRemoved: (int) ($data['entries_removed'] ?? 0),
            bytesFreed: (int) ($data['bytes_freed'] ?? 0),
            failure: Acknowledgement::clearFailure($statusCode ?? 200, (string) json_encode($data))
        ))->attachRaw($data);
    }

    /** Trident confirmed the clear (`cleared: true` on a 200). */
    public function isAcknowledged(): bool
    {
        return $this->failure === null;
    }
}
