<?php

declare(strict_types=1);

namespace Qoliber\Trident\Delivery;

use Psr\Log\LoggerInterface;
use Qoliber\Trident\Admin\PurgeOutbox;
use Qoliber\Trident\Config\Settings;
use Qoliber\Trident\Tags\TagPolicy;

/**
 * Durable purge delivery (the X02 contract) for one request (1.8.0) — the
 * platform-neutral part every integration shares; the platform supplies only
 * its {@see ScheduledStore} (its database) and, where its ORM can, the ids of
 * rows it added to the change's own transaction ({@see addOwn()}).
 *
 * Two ways a purge is recorded:
 * - a change the platform records itself (in the change's own transaction) adds rows to the SAME
 *   flush, so they commit (or roll back) with the change, and hands the ids
 *   here ({@see addOwn()});
 * - anything else (a proxy client, the admin screens, a CLI command):
 *   {@see recordTags()} through the library {@see Purger} (chunked, one
 *   transaction for every instance).
 *
 * {@see flush()} delivers the rows this request recorded, at the end of the
 * request; a row is removed only when its instance acknowledged it. Anything
 * refused or unreachable is retried with backoff by the platform's drain command
 * (cron or the platform's scheduler) and by an opportunistic drain at
 * the end of requests.
 */
final class OutboxDelivery implements PurgeOutbox
{
    public const DRAIN_LIMIT = 200;
    /**
     * Seconds after which a delivered purge is delivered once more: a render
     * that read the old data before the save committed and finished after the
     * first purge re-stored the stale page (the editor race).
     */
    public const REDELIVER_AFTER = 10;

    private readonly \Closure $clock;
    private readonly \Closure $transaction;
    private ?Purger $purger = null;
    /** @var list<int> */
    private array $own = [];

    public function __construct(
        private readonly OutboxStore $store,
        private readonly Settings $settings,
        private readonly TagPolicy $tags,
        private readonly Transport $transport,
        ?callable $transaction = null,
        ?callable $clock = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $redeliverAfter = self::REDELIVER_AFTER,
    ) {
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn (): int => time();
        $this->transaction = $transaction !== null ? \Closure::fromCallable($transaction) : static fn (callable $work): mixed => $work();
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function policy(): TagPolicy
    {
        return $this->tags;
    }

    /**
     * @param iterable<string> $tags unprefixed
     */
    public function recordTags(iterable $tags): int
    {
        return $this->recordTridentTags($this->tags->purgeTags($tags));
    }

    public function purgeTags(iterable $tags): array
    {
        return $this->tags->purgeTags($tags);
    }

    public function allTag(): string
    {
        return $this->tags->allTag();
    }

    public function recordAll(): int
    {
        return $this->recordTridentTags([$this->tags->allTag()]);
    }

    /**
     * @param list<string> $tags final (prefixed, normalised) tags
     */
    public function recordTridentTags(array $tags): int
    {
        if ($tags === [] || !$this->settings->enabled()) {
            return 0;
        }
        // All or nothing: every chunk for every instance in ONE transaction. A
        // failed write rolls the whole record back, the tags go out directly
        // at once, and the loss of durability is logged as critical.
        try {
            return (int) ($this->transaction)(function () use ($tags): int {
                $purger = $this->purger();
                $written = $purger->purgeTags($tags);
                if ($purger->recordError() !== null) {
                    throw new \RuntimeException('outbox write failed: ' . $purger->recordError());
                }

                return $written;
            });
        } catch (\Throwable $e) {
            $this->purger = null;
            $sent = $this->sendDirectly($tags);
            $this->logger?->critical('Trident: could not record a purge in the outbox; sent directly, without retry', [
                'error' => $e->getMessage(),
                'sent_directly' => $sent,
                'tags' => \array_slice($tags, 0, 50),
            ]);

            return 0;
        }
    }

    /**
     * Rows the platform added to the change's own transaction: delivered by
     * {@see flush()}.
     *
     * @param list<int> $ids
     */
    public function addOwn(array $ids): void
    {
        $this->own = array_values(array_unique(array_merge($this->own, $ids)));
    }

    public function hasOwn(): bool
    {
        return $this->own !== [] || $this->purger !== null;
    }

    /**
     * Deliver what this request recorded. Never throws.
     */
    public function flush(): DrainReport
    {
        try {
            $reports = [];
            if ($this->own !== []) {
                $ids = $this->own;
                $this->own = [];
                $entries = $this->store->byIds($ids);
                $now = ($this->clock)();
                $reports[] = $this->drainer()->deliverEntries($entries, $now);
                Purger::scheduleRedelivery($this->store, $entries, $now, $this->redeliverAfter);
            }
            if ($this->purger !== null) {
                $reports[] = $this->purger->deliverOwn();
            }

            return self::merge($reports);
        } catch (\Throwable $e) {
            $this->logger?->error('Trident: delivering this request\'s purges failed; the outbox keeps them', ['error' => $e->getMessage()]);

            return new DrainReport();
        }
    }

    public function drain(int $limit = self::DRAIN_LIMIT, bool $ignoreBackoff = false): DrainReport
    {
        if (!$this->settings->enabled()) {
            return new DrainReport();
        }

        return $this->drainer()->drain($limit, ($this->clock)(), $ignoreBackoff);
    }

    /**
     * Every row now, whatever its due time (`trident:purge:drain --now`).
     */
    public function drainAll(int $limit = self::DRAIN_LIMIT): DrainReport
    {
        if (!$this->settings->enabled()) {
            return new DrainReport();
        }

        return $this->drainer()->drainAll($limit, ($this->clock)());
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $stats = $this->store->stats(($this->clock)());
        $configured = array_flip($this->settings->instanceNames());
        $orphaned = [];
        foreach ($stats['by_instance'] ?? [] as $name => $count) {
            if (!isset($configured[$name])) {
                $orphaned[(string) $name] = (int) $count;
            }
        }
        $stats['orphaned'] = $orphaned;

        return $stats;
    }

    public function forget(string $instance): int
    {
        return $this->store->forget($instance);
    }

    /**
     * @param list<DrainReport> $reports
     */
    public static function merge(array $reports): DrainReport
    {
        $delivered = $failed = $purged = 0;
        $instances = [];
        foreach ($reports as $r) {
            $delivered += $r->delivered;
            $failed += $r->failed;
            $purged += $r->purged;
            foreach ($r->instances as $name => $info) {
                $instances[$name] = $info;
            }
        }

        return new DrainReport($delivered, $failed, $instances, $purged);
    }

    /**
     * @param list<string> $tags
     * @return array<string, bool>
     */
    private function sendDirectly(array $tags): array
    {
        $result = [];
        foreach ($this->settings->instances as $instance) {
            $ok = true;
            foreach (Packer::chunk($tags) as $chunk) {
                try {
                    $ok = (new PurgeClient($instance, $this->transport))->purgeTags($chunk, $this->settings->mode)->acknowledged() && $ok;
                } catch (\Throwable) {
                    $ok = false;
                }
            }
            $result[$instance->name] = $ok;
        }

        return $result;
    }

    private function drainer(): Drainer
    {
        return new Drainer($this->store, $this->settings->instances, $this->clientFactory(), $this->settings->mode);
    }

    private function purger(): Purger
    {
        return $this->purger ??= new Purger(
            $this->store,
            $this->settings->instances,
            $this->clientFactory(),
            $this->settings->mode,
            static function (): void {
            },
            $this->clock,
            Purger::DEFAULT_GRACE,
            $this->redeliverAfter,
        );
    }

    private function clientFactory(): \Closure
    {
        return fn (Instance $instance): PurgeClient => new PurgeClient($instance, $this->transport);
    }
}
