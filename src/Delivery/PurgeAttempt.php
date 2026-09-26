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
 * The outcome of one purge request.
 */
final class PurgeAttempt
{
    /**
     * @param string|null $failure     Null when Trident acknowledged it.
     * @param bool        $unreachable No HTTP response at all (the next request
     *                                 to the same instance would wait the same way).
     * @param int|null    $purged      Entries Trident reports it purged (or, for a
     *                                 clear, removed) — null when it did not say,
     *                                 e.g. a deferred (202 recorded) purge (1.5.0).
     * @param string|null $state       The engine's `state` (`recorded`, …) when given.
     */
    public function __construct(
        public readonly ?string $failure,
        public readonly bool $unreachable = false,
        public readonly ?int $purged = null,
        public readonly ?string $state = null
    ) {
    }

    /**
     * The attempt for an HTTP answer: the failure as Acknowledgement judged it,
     * plus the counts the answer carries (1.5.0).
     */
    public static function fromAnswer(?string $failure, string $body): self
    {
        $decoded = json_decode($body, true);
        $purged = null;
        $state = null;
        if (is_array($decoded)) {
            $count = $decoded['purged'] ?? $decoded['entries_removed'] ?? null;
            $purged = is_int($count) ? $count : null;
            $state = is_string($decoded['state'] ?? null) ? $decoded['state'] : null;
        }
        return new self($failure, false, $purged, $state);
    }

    public function acknowledged(): bool
    {
        return $this->failure === null;
    }
}
