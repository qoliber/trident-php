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
 * Retry schedule for invalidations Trident did not acknowledge: 1, 2, 4 … 256
 * seconds, then every 300. Capped so a Trident that is down is retried every
 * few minutes rather than hammered; never given up on, because a stale page is
 * served until the purge lands, however late.
 */
final class Backoff
{
    public const MAX_DELAY = 300;

    /**
     * @param int $failures Failed attempts so far, including the one just made.
     * @return int Seconds until the next attempt.
     */
    public static function delay(int $failures): int
    {
        $exponent = max(0, min($failures - 1, 16));
        return (int) min(2 ** $exponent, self::MAX_DELAY);
    }

    public static function nextAttemptAt(int $failures, int $now): int
    {
        return $now + self::delay($failures);
    }
}
