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
 * One outbox row: tags owed to one instance.
 */
final class OutboxEntry
{
    /**
     * @param int          $id       Row id; ascending = oldest first.
     * @param string       $instance Instance the purge is owed to.
     * @param list<string> $tags     Tags to purge.
     * @param int          $attempts Failed deliveries so far.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $instance,
        public readonly array $tags,
        public readonly int $attempts
    ) {
    }
}
