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
 * X02 — record now, deliver after the request, remove only when acknowledged.
 *
 * The single entry point a platform calls when data changes. A purge is
 * RECORDED in the outbox the moment the change is known and DELIVERED by this
 * process when the platform's `$defer` callback runs it — at the end of the
 * request, after every write of the save has happened, so a soft purge's
 * background refresh never re-renders the old data. It is removed only when
 * Trident acknowledges it; a 401, 429, 5xx, timeout or a process killed before
 * the end of the request leaves it for the next drain (the platform's cron, an
 * operator command). Purges are idempotent: a crash between "sent" and
 * "removed" costs one extra purge, never a lost one.
 *
 * Not every platform saves inside one transaction. WordPress/WooCommerce write
 * a product in several autocommitted steps, and the first hook that records a
 * purge (the post status transition) runs BEFORE the new price is written. So
 * a new row is due only after a grace period: other drainers (cron, another
 * request's end-of-request drain, an operator drain) leave it alone while this
 * process finishes, and this process delivers its OWN rows by id at the end,
 * ignoring the grace. If the process dies, the row becomes due for everyone
 * when the grace expires.
 *
 * X03 — one row per instance, each acknowledged and retried on its own.
 */
final class Purger
{
    /** Entries a drain looks at from a shop request. */
    public const REQUEST_DRAIN_LIMIT = 50;

    /** Seconds a new row belongs to the process that wrote it. */
    public const DEFAULT_GRACE = 120;

    /**
     * Tags recorded by this process and not yet delivered by it, per instance,
     * with the time they were recorded: one save fires several hooks, and each
     * would otherwise write the same tags again. A tag recorded longer ago than
     * half the grace is recorded again — its row may be due for other drainers
     * already, and a later change must not ride on a purge that could have gone
     * out before it (long-running processes: imports, queue runners).
     *
     * @var array<string, array<string, int>>
     */
    private array $recorded = [];

    /**
     * Row ids this process recorded and still has to deliver, per instance.
     *
     * @var array<string, list<int>>
     */
    private array $own = [];

    /**
     * Fallback only: tags that could not be recorded (the outbox table is
     * missing), sent best-effort at delivery — the pre-X02 behaviour — rather
     * than not at all.
     *
     * @var array<string, array<string, true>>
     */
    private array $unrecorded = [];

    private bool $deliveryArmed = false;

    private ?string $recordError = null;

    /** @var \Closure(Instance): PurgeClient */
    private readonly \Closure $clientFactory;

    /** @var \Closure(callable): void */
    private readonly \Closure $defer;

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /**
     * @param list<Instance>                  $instances
     * @param callable(Instance): PurgeClient $clientFactory
     * @param string                          $mode  soft|hard.
     * @param callable(callable): void        $defer Runs the given callable at the end of the
     *                                               request (WordPress: `shutdown`; Magento: a
     *                                               commit callback; Symfony: `kernel.terminate`).
     * @param (callable(): int)|null          $clock Unix time; `time()` by default.
     * @param int                             $grace Seconds other drainers leave a new row alone.
     */
    public function __construct(
        private readonly OutboxStore $store,
        private readonly array $instances,
        callable $clientFactory,
        private readonly string $mode,
        callable $defer,
        ?callable $clock = null,
        private readonly int $grace = self::DEFAULT_GRACE
    ) {
        $this->clientFactory = \Closure::fromCallable($clientFactory);
        $this->defer = \Closure::fromCallable($defer);
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Record a purge of `$tags` for every instance; delivery is deferred.
     *
     * @param list<string> $tags Final tags (prefixed and normalised).
     * @return int Rows recorded.
     */
    public function purgeTags(array $tags): int
    {
        $tags = array_values(array_unique(array_filter(
            array_map('strval', $tags),
            static fn (string $t): bool => $t !== ''
        )));
        if ($tags === [] || $this->instances === []) {
            return 0;
        }
        $now = ($this->clock)();
        $fresh = max(1, intdiv($this->grace, 2));
        $written = 0;
        foreach ($this->instances as $instance) {
            $new = [];
            foreach ($tags as $tag) {
                $at = $this->recorded[$instance->name][$tag] ?? null;
                if ($at === null || $now - $at >= $fresh) {
                    $new[] = $tag;
                }
            }
            foreach (Packer::chunk($new) as $chunk) {
                try {
                    $this->own[$instance->name][] = $this->store->record($instance->name, $chunk, $now, $now + $this->grace);
                } catch (\Throwable $e) {
                    $this->recordError = $e->getMessage();
                    foreach ($chunk as $tag) {
                        $this->unrecorded[$instance->name][$tag] = true;
                    }
                    $this->armDelivery();
                    continue;
                }
                foreach ($chunk as $tag) {
                    $this->recorded[$instance->name][$tag] = $now;
                }
                ++$written;
            }
        }
        if ($written > 0) {
            $this->armDelivery();
        }
        return $written;
    }

    /**
     * Deliver due entries now (cron, CLI, an admin button). Rows other
     * processes are still writing (in their grace period) are not due.
     *
     * @param bool $ignoreBackoff Operator "deliver now": also rows backing off after a failure.
     */
    public function drain(int $limit = self::REQUEST_DRAIN_LIMIT, bool $ignoreBackoff = false): DrainReport
    {
        return $this->drainer()->drain($limit, ($this->clock)(), $ignoreBackoff);
    }

    /**
     * Deliver this process's own rows, whatever their grace, and forget them.
     */
    public function deliverOwn(): DrainReport
    {
        $ids = [];
        foreach ($this->own as $list) {
            $ids = array_merge($ids, $list);
        }
        $this->own = [];
        $this->recorded = [];
        if ($ids === []) {
            return new DrainReport();
        }
        return $this->drainer()->deliverEntries($this->store->byIds($ids), ($this->clock)());
    }

    /**
     * The deferred delivery: own rows first (the save is complete now), then
     * whatever else is due. Never throws — it runs in shutdown handlers, where
     * an exception is a fatal error on the shop's page.
     */
    public function deliverPending(): void
    {
        $this->deliveryArmed = false;
        try {
            foreach ($this->instances as $instance) {
                $tags = array_map('strval', array_keys($this->unrecorded[$instance->name] ?? []));
                foreach (Packer::chunk($tags) as $chunk) {
                    ($this->clientFactory)($instance)->purgeTags($chunk, $this->mode);
                }
            }
            $this->unrecorded = [];
            $this->deliverOwn();
            $this->drain();
        } catch (\Throwable $e) {
            // What was not acknowledged stays in the outbox; cron retries it.
            $this->recordError = 'delivery: ' . $e->getMessage();
        }
    }

    /**
     * Why the last record (or deferred delivery) failed, if one did.
     */
    public function recordError(): ?string
    {
        return $this->recordError;
    }

    /**
     * @return list<Instance>
     */
    public function instances(): array
    {
        return $this->instances;
    }

    private function drainer(): Drainer
    {
        return new Drainer($this->store, $this->instances, $this->clientFactory, $this->mode);
    }

    private function armDelivery(): void
    {
        if ($this->deliveryArmed) {
            return;
        }
        $this->deliveryArmed = true;
        ($this->defer)([$this, 'deliverPending']);
    }
}
