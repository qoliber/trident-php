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
 * The part of the admin API invalidation delivery needs, for one instance,
 * over a Transport. Every answer is judged by Acknowledgement.
 */
final class PurgeClient
{
    public function __construct(
        private readonly Instance $instance,
        private readonly Transport $transport
    ) {
    }

    public function instance(): Instance
    {
        return $this->instance;
    }

    /**
     * POST /admin/purge/tags.
     *
     * @param list<string> $tags At most Packer::MAX_TAGS_PER_REQUEST.
     * @param string       $mode soft|hard.
     */
    public function purgeTags(array $tags, string $mode): PurgeAttempt
    {
        $response = $this->transport->request(
            'POST',
            $this->instance->apiUrl . '/admin/purge/tags',
            $this->headers(),
            (string) json_encode(['tags' => array_values($tags), 'mode' => $mode === 'hard' ? 'hard' : 'soft'])
        );
        if ($response['status'] === 0) {
            return new PurgeAttempt(self::noResponse($response['error']), true);
        }
        return PurgeAttempt::fromAnswer(Acknowledgement::purgeFailure($response['status'], $response['body']), $response['body']);
    }

    /**
     * Remove every entry on this instance (1.5.0), judged like a purge: the
     * clear schema (`cleared: true` on a 200) is the acknowledgement, anything
     * else a failure worth retrying. The removed-entry count is in `purged`.
     */
    public function clear(): PurgeAttempt
    {
        $response = $this->transport->request(
            'POST',
            $this->instance->apiUrl . '/admin/cache/clear',
            $this->headers(),
            (string) json_encode(['confirm' => true])
        );
        if ($response['status'] === 0) {
            return new PurgeAttempt(self::noResponse($response['error']), true);
        }
        return PurgeAttempt::fromAnswer(Acknowledgement::clearFailure($response['status'], $response['body']), $response['body']);
    }

    /**
     * GET /admin/status — for a "test connection" button or CLI.
     *
     * The URL is whatever an administrator typed, so nothing the server says is
     * returned verbatim: a status code class, a version only if it looks like a
     * version, a licence mode only from the known set. Otherwise the button
     * would read out internal hosts' error pages and banners (an SSRF oracle).
     *
     * @return array{ok: bool, message: string}
     */
    public function status(): array
    {
        $response = $this->transport->request('GET', $this->instance->apiUrl . '/admin/status', $this->headers(), null);
        $status = $response['status'];
        if ($status === 0) {
            return ['ok' => false, 'message' => 'no response'];
        }
        $decoded = json_decode($response['body'], true);
        if ($status !== 200) {
            return ['ok' => false, 'message' => sprintf('HTTP %d — %s', $status, self::statusClass($status))];
        }
        if (!is_array($decoded) || (!isset($decoded['version']) && !isset($decoded['status']))) {
            return ['ok' => false, 'message' => 'HTTP 200 — not a Trident admin API'];
        }
        $version = is_string($decoded['version'] ?? null)
            && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}(?:[-+][0-9A-Za-z.]{1,20})?$/', $decoded['version']) === 1
            ? $decoded['version'] : '(unrecognised version)';
        $mode = in_array($decoded['mode'] ?? null, ['licensed', 'unlicensed', 'degraded', 'trial', 'expired', 'grace'], true)
            ? (string) $decoded['mode'] : 'unknown';
        return ['ok' => true, 'message' => sprintf('Trident %s, license: %s', $version, $mode)];
    }

    private static function statusClass(int $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'token rejected',
            $status === 404 => 'not found (is this the admin port?)',
            $status === 429 => 'rate limited',
            $status >= 500 => 'server error',
            $status >= 300 && $status < 400 => 'redirect (not followed)',
            default => 'unexpected response',
        };
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return \Qoliber\Trident\Admin\Api::headers($this->instance);
    }

    private static function noResponse(?string $error): string
    {
        return 'no response' . ($error !== null && $error !== '' ? ' — ' . $error : '');
    }
}
