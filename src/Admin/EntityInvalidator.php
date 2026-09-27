<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

/**
 * Optional for a {@see ShopAdapter}: purge entities through the platform's own
 * invalidation (e.g. Shopware's CacheInvalidator, which also clears the
 * platform's caches and records Trident's purge through its gateway), instead
 * of recording the tags in the outbox directly.
 */
interface EntityInvalidator
{
    /**
     * @param list<string> $tags from {@see ShopAdapter::entityTags()}
     */
    public function invalidate(array $tags): void;
}
