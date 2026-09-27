<?php

declare(strict_types=1);

namespace Qoliber\Trident\Config;

use Qoliber\Trident\Delivery\Instance;

/**
 * The resolved Trident configuration for one request: which instances, where
 * they came from, and why an instance was dropped. Never holds a sealed token;
 * an instance's token is the plain token for THAT instance's URL.
 */
final class Settings
{
    public const MODE_SOFT = 'soft';
    public const MODE_HARD = 'hard';
    /** The platform could not read its stored settings (see {@see SettingsResolver}). */
    public const READ_FAILED = 'The Trident settings could not be read: purges are recorded for the admin instance "default" and delivered once they can be read';

    /**
     * @param list<Instance> $instances
     * @param list<string>   $errors
     */
    public function __construct(
        public readonly array $instances,
        public readonly array $errors,
        public readonly string $source,
        public readonly string $mode,
        public readonly string $tagPrefix,
        public readonly bool $debugHeaders,
        public readonly bool $readFailed = false,
    ) {
    }

    public function enabled(): bool
    {
        return $this->instances !== [];
    }

    /**
     * @return list<string>
     */
    public function instanceNames(): array
    {
        return array_map(static fn (Instance $i): string => $i->name, $this->instances);
    }
}
