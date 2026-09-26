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
 * One Trident server a platform invalidates: its admin API and token.
 */
final class Instance
{
    /**
     * @param string $name     Stable name. Outbox rows are owed to it, so renaming an
     *                         instance leaves its pending purges behind.
     * @param string $apiUrl   Admin API base URL without a trailing slash.
     * @param string $apiToken Bearer token, plain text.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $apiUrl,
        public readonly string $apiToken
    ) {
    }
}
