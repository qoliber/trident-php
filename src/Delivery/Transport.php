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
 * One HTTP request to a Trident admin API.
 *
 * The delivery code needs only this, so a platform can use its own HTTP stack
 * (the WordPress HTTP API, Magento's Curl) instead of a PSR-18 client — see
 * Psr18Transport for platforms that have one.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, error: string|null} `status` 0 and an
     *         `error` when no HTTP response arrived (refused, timeout, DNS).
     */
    public function request(string $method, string $url, array $headers, ?string $body): array;
}
