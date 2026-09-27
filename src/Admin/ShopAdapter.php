<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

/**
 * What the admin screens need to know about the shop — the platform part of
 * {@see AdminService} (1.7.0).
 */
interface ShopAdapter
{
    /**
     * The storefront hosts as Trident keys them (`host[:port]`, lower case).
     *
     * @return list<string>
     */
    public function hosts(): array;

    /** `http` or `https` for one of {@see hosts()}. */
    public function scheme(string $host): string;

    /**
     * Absolute URLs of the catalogue (home, listings, products), for coverage
     * and warming.
     *
     * @return list<string>
     */
    public function catalogUrls(int $limit): array;

    /**
     * Entity kinds an admin may purge by id, `kind => label`.
     *
     * @return array<string, string>
     */
    public function entityKinds(): array;

    /**
     * The (unprefixed, platform) tags that purge these entities.
     *
     * @param list<string> $ids
     * @return list<string>
     * @throws \InvalidArgumentException naming an id that is not one
     */
    public function entityTags(string $kind, array $ids): array;
}
