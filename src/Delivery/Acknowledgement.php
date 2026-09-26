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
 * X02 — what counts as Trident having taken an invalidation.
 *
 * A purge is removed from an outbox only when this says so. Everything else —
 * 401 after a token change, 429 from the admin limiter, a 5xx, a timeout, a
 * proxy's HTML error page, a 200 carrying an error object, a redirect to a
 * login page — is not a purge that happened.
 */
final class Acknowledgement
{
    /**
     * `PurgeResponse` (trident-core admin/models.rs): `purged` (int) and `mode`
     * (string) are sent by every engine version. 1.8 adds `state`, where
     * `refused` means "not taken; retry". Requiring `state` would retry every
     * purge forever against the 1.6/1.7 engines still in production.
     *
     * @param array<mixed> $body Decoded response body.
     */
    public static function isPurgeAck(array $body): bool
    {
        return is_int($body['purged'] ?? null)
            && is_string($body['mode'] ?? null)
            && (!array_key_exists('state', $body)
                || (is_string($body['state']) && $body['state'] !== 'refused'));
    }

    /**
     * `CacheClearResponse`: `cleared` must be true.
     *
     * @param array<mixed> $body Decoded response body.
     */
    public static function isClearAck(array $body): bool
    {
        return ($body['cleared'] ?? null) === true;
    }

    /**
     * Why a purge response is not an acknowledgement, or null when it is.
     *
     * @param int    $status HTTP status; 0 when no response arrived.
     * @param string $body   Raw response body.
     */
    public static function purgeFailure(int $status, string $body): ?string
    {
        if ($status === 202 && self::isDeferredAck((array) json_decode($body, true))) {
            return null;
        }
        return self::failure($status, $body, [self::class, 'isPurgeAck']);
    }

    /**
     * Reflect mode (trident-core admin/handlers/reflect.rs): the purge is
     * durably queued and applied when reflect ends — HTTP 202 with
     * `state: "recorded"`. That is taken, not lost: retrying it every few
     * minutes through a long incident would only flood the reflect queue with
     * duplicates. A full reflect queue answers 503 with `state: "refused"` and
     * stays not acknowledged.
     *
     * @param array<mixed> $body Decoded response body.
     */
    public static function isDeferredAck(array $body): bool
    {
        return ($body['state'] ?? null) === 'recorded';
    }

    /**
     * Why a cache-clear response is not an acknowledgement, or null when it is.
     */
    public static function clearFailure(int $status, string $body): ?string
    {
        return self::failure($status, $body, [self::class, 'isClearAck']);
    }

    /**
     * @param callable(array<mixed>): bool $isAck
     */
    private static function failure(int $status, string $body, callable $isAck): ?string
    {
        $decoded = json_decode($body, true);
        if ($status === 200 && is_array($decoded) && $isAck($decoded)) {
            return null;
        }
        if ($status === 0) {
            return 'no response';
        }
        $error = null;
        if (is_array($decoded)) {
            $error = $decoded['error']
                ?? (isset($decoded['state']) && $decoded['state'] === 'refused' ? 'purge refused' : null);
        }
        if ($status === 200 && $error === null) {
            $error = is_array($decoded) ? 'not an acknowledgement' : 'response is not JSON';
        }
        return sprintf('HTTP %d%s', $status, is_string($error) && $error !== '' ? ' — ' . self::clip($error) : '');
    }

    private static function clip(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        return strlen($text) > 200 ? substr($text, 0, 197) . '...' : $text;
    }
}
