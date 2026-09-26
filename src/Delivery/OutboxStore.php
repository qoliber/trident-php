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
 * X02 — where invalidation intents live between "the data changed" and
 * "Trident took it". Each platform implements it on its own database layer
 * (Magento ResourceConnection, WordPress $wpdb, Doctrine DBAL for Shopware and
 * Sylius) and writes through the SAME connection the platform saves entities
 * with. Where the platform wraps a save in a transaction (Magento), the intent
 * then commits or rolls back with the data.
 *
 * Where it does not — WordPress and WooCommerce save a product in several
 * autocommitted steps — a row can be visible before the save has finished.
 * Rows are therefore recorded with a later due time (Purger's grace period):
 * other drainers skip them while the writing process finishes, and that
 * process delivers its own rows by id (byIds()) when it is done.
 *
 * Instance names are compared exactly (case-sensitive) — use a binary
 * collation. Times are Unix seconds supplied by the caller.
 */
interface OutboxStore
{
    /**
     * Record tags owed to one instance.
     *
     * @param list<string> $tags  At most Packer::MAX_TAGS_PER_REQUEST.
     * @param int          $dueAt When other drainers may take it (the writer's grace).
     * @return int Row id.
     * @throws \Throwable When the intent could not be recorded; the caller falls
     *                    back to best-effort delivery.
     */
    public function record(string $instance, array $tags, int $now, int $dueAt): int;

    /**
     * Rows that still exist, by id, whatever their due time — the writing
     * process delivering its own rows. Unknown ids (delivered meanwhile by
     * someone else) are skipped.
     *
     * @param list<int> $ids
     * @return list<OutboxEntry>
     */
    public function byIds(array $ids): array;

    /**
     * Entries whose next attempt is due, oldest first.
     *
     * @param bool         $ignoreBackoff Include entries backing off after a FAILED attempt —
     *                                    an operator's "deliver now". Rows never attempted
     *                                    (attempts = 0) are still in their writer's grace
     *                                    period and stay excluded until due.
     * @param list<string> $instances     Only entries owed to these (exact match).
     * @return list<OutboxEntry>
     */
    public function due(int $limit, int $now, bool $ignoreBackoff, array $instances): array;

    /**
     * Drop acknowledged entries. Unknown ids are ignored: a concurrent drain may
     * have delivered them already, and delivery is idempotent.
     *
     * @param list<int> $ids
     */
    public function remove(array $ids): void;

    /**
     * Keep entries after a failed attempt: attempts + 1, next attempt at
     * Backoff::nextAttemptAt(attempts + 1, $now), the reason recorded.
     *
     * @param list<OutboxEntry> $entries
     */
    public function fail(array $entries, string $reason, int $now): void;

    /**
     * Drop everything owed to an instance that is gone for good.
     *
     * @return int Entries removed.
     */
    public function forget(string $instance): int;

    /**
     * @return array{pending: int, oldest_age: int|null, last_error: string|null, last_error_at: int|null, by_instance: array<string, int>}
     */
    public function stats(int $now): array;
}
