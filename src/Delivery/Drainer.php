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
 * X02/X03 — delivers due outbox entries and removes each one Trident
 * acknowledges.
 *
 * Each instance is delivered on its own. An instance that is down keeps its
 * own entries pending and is tried at most once per drain when unreachable (or
 * three times when it answers with rejections), so it never holds back the
 * others — and a shop request that happens to drain does not wait out a dead
 * edge several times. Entries are merged into requests of at most 1000 unique
 * tags; an acknowledged request removes exactly the entries it carried.
 */
final class Drainer
{
    /** Consecutive unacknowledged requests after which an instance is left for the next drain. */
    public const MAX_CONSECUTIVE_FAILURES = 3;

    /** @var \Closure(Instance): PurgeClient */
    private readonly \Closure $clientFactory;

    /**
     * @param list<Instance>                        $instances     Configured instances.
     * @param callable(Instance): PurgeClient       $clientFactory
     * @param string                                $mode          soft|hard.
     */
    public function __construct(
        private readonly OutboxStore $store,
        private readonly array $instances,
        callable $clientFactory,
        private readonly string $mode = 'soft'
    ) {
        $this->clientFactory = \Closure::fromCallable($clientFactory);
    }

    /**
     * @param bool $ignoreBackoff Deliver entries still in backoff too — an
     *                            operator's "deliver now" after fixing the cause
     *                            must not wait out a retry schedule.
     */
    public function drain(int $limit, int $now, bool $ignoreBackoff = false): DrainReport
    {
        if ($this->instances === []) {
            return new DrainReport();
        }
        $names = array_map(static fn (Instance $i): string => $i->name, $this->instances);
        // Only entries owed to configured instances: rows owed to one that was
        // removed must not take the drain's slots forever. A status command
        // reports them; OutboxStore::forget() drops them.
        return $this->deliverEntries($this->store->due($limit, $now, $ignoreBackoff, $names), $now);
    }

    /**
     * "Deliver now": every row owed to a configured instance, whatever its due
     * time — a scheduled second delivery, a backstop row, a row still in its
     * writer's grace, one in backoff. For operators after an incident and for
     * test suites; a failure is retried on the real clock ($now).
     */
    public function drainAll(int $limit, int $now): DrainReport
    {
        if ($this->instances === []) {
            return new DrainReport();
        }
        $names = array_map(static fn (Instance $i): string => $i->name, $this->instances);

        return $this->deliverEntries($this->store->due($limit, PHP_INT_MAX, true, $names), $now);
    }

    /**
     * Deliver the given entries (the writing process's own rows, or a due batch).
     *
     * @param list<OutboxEntry> $entries
     */
    public function deliverEntries(array $entries, int $now): DrainReport
    {
        $byName = [];
        foreach ($this->instances as $instance) {
            $byName[$instance->name] = $instance;
        }
        $grouped = [];
        foreach ($entries as $entry) {
            if (isset($byName[$entry->instance])) {
                $grouped[$entry->instance][] = $entry;
            }
        }
        $delivered = 0;
        $failed = 0;
        $purged = 0;
        $report = [];
        foreach ($grouped as $name => $owed) {
            $result = $this->deliver(($this->clientFactory)($byName[$name]), $owed, $now);
            $report[(string) $name] = $result;
            $delivered += $result['delivered'];
            $failed += $result['failed'];
            $purged += $result['purged'];
        }
        return new DrainReport($delivered, $failed, $report, $purged);
    }

    /**
     * @param list<OutboxEntry> $entries Oldest first.
     * @return array{delivered: int, failed: int, error: string|null, purged: int}
     */
    private function deliver(PurgeClient $client, array $entries, int $now): array
    {
        $delivered = 0;
        $failed = 0;
        $purged = 0;
        $failures = 0;
        $error = null;
        foreach (Packer::pack($entries) as $request) {
            $ids = array_map(static fn (OutboxEntry $e): int => $e->id, $request['entries']);
            $attempt = $client->purgeTags($request['tags'], $this->mode);
            if ($attempt->acknowledged()) {
                $this->store->remove($ids);
                $delivered += count($ids);
                $purged += $attempt->purged ?? 0;
                $failures = 0;
                continue;
            }
            $error = $attempt->failure;
            $this->store->fail($request['entries'], (string) $attempt->failure, $now);
            $failed += count($ids);
            ++$failures;
            if ($attempt->unreachable || $failures >= self::MAX_CONSECUTIVE_FAILURES) {
                break;
            }
        }
        return ['delivered' => $delivered, 'failed' => $failed, 'error' => $error, 'purged' => $purged];
    }
}
