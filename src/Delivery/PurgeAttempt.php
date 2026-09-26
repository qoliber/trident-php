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
 * The outcome of one purge request.
 */
final class PurgeAttempt
{
    /**
     * @param string|null $failure     Null when Trident acknowledged it.
     * @param bool        $unreachable No HTTP response at all (the next request
     *                                 to the same instance would wait the same way).
     */
    public function __construct(
        public readonly ?string $failure,
        public readonly bool $unreachable = false
    ) {
    }

    public function acknowledged(): bool
    {
        return $this->failure === null;
    }
}
