<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Testing;

use Qoliber\Trident\Delivery\Backoff;
use Qoliber\Trident\Delivery\OutboxEntry;
use Qoliber\Trident\Delivery\NotBeforeStore;
use Qoliber\Trident\Delivery\OutboxStore;

/**
 * An OutboxStore in memory, with the semantics every database implementation
 * must have: exact (case-sensitive) instance match, oldest first, a backoff per
 * entry. A test double — for this library's tests and for platform packages
 * that test their Purger wiring without a database. It lives in `src/` (not
 * `tests/`) because Composer never loads a dependency's autoload-dev, so a
 * double under `tests/` could not be reused by another package.
 *
 * @internal Not for production use.
 */
class InMemoryOutboxStore implements NotBeforeStore
{
    /** @var array<int, array{instance: string, tags: list<string>, attempts: int, created_at: int, next_attempt_at: int, last_error: ?string, last_error_at: ?int}> */
    public array $rows = [];

    /** Make record() throw, like a missing table. */
    public bool $broken = false;

    private int $nextId = 1;

    public function record(string $instance, array $tags, int $now, int $dueAt): int
    {
        if ($this->broken) {
            throw new \RuntimeException("Table 'trident_purge_outbox' doesn't exist");
        }
        $this->rows[$this->nextId++] = [
            'instance' => $instance,
            'tags' => array_values($tags),
            'attempts' => 0,
            'created_at' => $now,
            'next_attempt_at' => $dueAt,
            'last_error' => null,
            'last_error_at' => null,
        ];
        return $this->nextId - 1;
    }

    /** @var array<int, true> ids of not-before rows */
    public array $notBefore = [];

    public function recordScheduled(string $instance, array $tags, int $now, int $dueAt): int
    {
        $this->record($instance, $tags, $now, $dueAt);

        return 1;
    }

    public function recordNotBefore(string $instance, array $tags, int $now, int $notBefore): int
    {
        foreach ($this->rows as $id => $row) {
            if (isset($this->notBefore[$id]) && $row['instance'] === $instance && $row['tags'] === array_values($tags) && $row['next_attempt_at'] === $notBefore) {
                return 0;
            }
        }
        $id = $this->record($instance, $tags, $now, $notBefore);
        $this->notBefore[$id] = true;

        return 1;
    }

    public function dueEarly(int $limit, int $now, array $instances): array
    {
        $out = [];
        foreach ($this->rows as $id => $row) {
            if (!in_array($row['instance'], $instances, true)) {
                continue;
            }
            if (isset($this->notBefore[$id]) && $row['next_attempt_at'] > $now) {
                continue;
            }
            $out[] = new OutboxEntry($id, $row['instance'], $row['tags'], $row['attempts']);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function byIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->rows[$id])) {
                $row = $this->rows[$id];
                $out[] = new OutboxEntry($id, $row['instance'], $row['tags'], $row['attempts']);
            }
        }
        usort($out, static fn (OutboxEntry $a, OutboxEntry $b): int => $a->id <=> $b->id);
        return $out;
    }

    public function due(int $limit, int $now, bool $ignoreBackoff, array $instances): array
    {
        $out = [];
        foreach ($this->rows as $id => $row) {
            if (!in_array($row['instance'], $instances, true)) {
                continue;
            }
            if ($row['next_attempt_at'] > $now && !($ignoreBackoff && $row['attempts'] > 0)) {
                continue;
            }
            $out[] = new OutboxEntry($id, $row['instance'], $row['tags'], $row['attempts']);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function remove(array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->rows[$id]);
        }
    }

    public function fail(array $entries, string $reason, int $now): void
    {
        foreach ($entries as $entry) {
            if (!isset($this->rows[$entry->id])) {
                continue;
            }
            $failures = $entry->attempts + 1;
            $this->rows[$entry->id]['attempts'] = $failures;
            $this->rows[$entry->id]['next_attempt_at'] = Backoff::nextAttemptAt($failures, $now);
            $this->rows[$entry->id]['last_error'] = $reason;
            $this->rows[$entry->id]['last_error_at'] = $now;
        }
    }

    public function forget(string $instance): int
    {
        $removed = 0;
        foreach ($this->rows as $id => $row) {
            if ($row['instance'] === $instance) {
                unset($this->rows[$id]);
                ++$removed;
            }
        }
        return $removed;
    }

    public function stats(int $now): array
    {
        $by = [];
        $oldest = null;
        $last = null;
        $lastAt = null;
        foreach ($this->rows as $row) {
            $by[$row['instance']] = ($by[$row['instance']] ?? 0) + 1;
            $oldest = $oldest === null ? $row['created_at'] : min($oldest, $row['created_at']);
            if ($row['last_error_at'] !== null && ($lastAt === null || $row['last_error_at'] >= $lastAt)) {
                $last = $row['last_error'];
                $lastAt = $row['last_error_at'];
            }
        }
        ksort($by);
        return [
            'pending' => count($this->rows),
            'oldest_age' => $oldest === null ? null : $now - $oldest,
            'last_error' => $last,
            'last_error_at' => $lastAt,
            'by_instance' => $by,
        ];
    }

    /**
     * @return list<string> Every tag pending for an instance.
     */
    public function tagsFor(string $instance): array
    {
        $tags = [];
        foreach ($this->rows as $row) {
            if ($row['instance'] === $instance) {
                $tags = array_merge($tags, $row['tags']);
            }
        }
        return $tags;
    }
}
