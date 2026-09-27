<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Qoliber\Trident\Delivery\DrainReport;

/**
 * The platform's durable purge delivery for one request, as the admin
 * screens use it (1.7.0).
 */
interface PurgeOutbox
{
    /**
     * Platform tags → the tags a purge sends (prefix, normalisation, overflow).
     *
     * @param iterable<string> $tags
     * @return list<string>
     */
    public function purgeTags(iterable $tags): array;

    /** The shop-wide tag (on every page). */
    public function allTag(): string;

    /**
     * @param list<string> $tags final tags
     */
    public function recordTridentTags(array $tags): int;

    public function recordAll(): int;

    /** Deliver what this request recorded. */
    public function flush(): DrainReport;

    public function drain(int $limit, bool $ignoreBackoff): DrainReport;

    /**
     * @return array<string, mixed>
     */
    public function status(): array;
}
