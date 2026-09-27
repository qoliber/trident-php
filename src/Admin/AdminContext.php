<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Qoliber\Trident\Delivery\Instance;

/**
 * The platform's resolved configuration for one request (1.7.0).
 */
interface AdminContext
{
    /**
     * @return list<Instance>
     */
    public function instances(): array;

    /** `soft` or `hard`. */
    public function mode(): string;

    /**
     * The settings as the screens show them — never a token.
     *
     * @return array<string, mixed>
     */
    public function view(): array;

    public function outbox(): PurgeOutbox;
}
