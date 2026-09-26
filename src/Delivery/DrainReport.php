<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

/**
 * What one drain did.
 */
final class DrainReport
{
    /**
     * @param int $delivered Entries acknowledged and removed.
     * @param int $failed    Entries sent and not acknowledged (kept, backed off).
     * @param array<string, array{delivered: int, failed: int, error: string|null, purged?: int}> $instances
     *        `purged` (1.5.0): cache entries Trident reported purging for this instance.
     * @param int $purged Cache entries Trident reported purging, all instances
     *                    (1.5.0; deferred purges report none).
     */
    public function __construct(
        public readonly int $delivered = 0,
        public readonly int $failed = 0,
        public readonly array $instances = [],
        public readonly int $purged = 0
    ) {
    }
}
