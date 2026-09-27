<?php

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

/**
 * An outbox that can hold rows that are SCHEDULED, not owed (1.7.0): a
 * second delivery ({@see Purger::scheduleRedelivery()}) or a platform's
 * backstop. They are delivered like any row once due, but a status report
 * does not count them as pending before then. A store that does not
 * implement this records them as ordinary rows.
 */
interface ScheduledStore extends OutboxStore
{
    /**
     * @param list<string> $tags
     * @return int rows recorded (an identical scheduled set may be deduplicated: 0)
     */
    public function recordScheduled(string $instance, array $tags, int $now, int $dueAt): int;
}
