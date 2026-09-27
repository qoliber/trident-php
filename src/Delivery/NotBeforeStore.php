<?php

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

/**
 * An outbox that also holds purges that must NOT happen before a moment
 * (1.8.0): a scheduled price's start or end — delivered early, the purge
 * re-caches the old price and the change is never purged.
 *
 * Every other scheduled row (a second delivery, a backstop, a row in its
 * writer's grace or in backoff) may be delivered early; {@see Drainer::drainAll()}
 * delivers those, and holds these back until their time.
 */
interface NotBeforeStore extends ScheduledStore
{
    /**
     * @param list<string> $tags
     * @return int rows recorded (an identical row for the same moment: 0)
     */
    public function recordNotBefore(string $instance, array $tags, int $now, int $notBefore): int;

    /**
     * Rows "deliver now" may send: every row owed to these instances, except
     * not-before rows whose moment has not come.
     *
     * @param list<string> $instances
     * @return list<OutboxEntry>
     */
    public function dueEarly(int $limit, int $now, array $instances): array;
}
