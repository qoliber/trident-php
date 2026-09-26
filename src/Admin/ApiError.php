<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Qoliber\Trident\Exception\TridentException;

/**
 * A failed admin API call. `getCode()` is the HTTP status (0 = no response).
 *
 * `engineCode` is the machine code the engine puts in its error body
 * (`REFLECT_DISABLED`, `WARMER_DISABLED`, `LAUNCH_ACTIVE`, …) when it gives
 * one — a screen uses it to tell "this feature is not enabled on this
 * instance" from a real failure.
 */
final class ApiError extends TridentException
{
    public function __construct(
        string $message,
        int $status,
        public readonly ?string $engineCode = null,
        public readonly ?string $engineMessage = null
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(int $status, string $body): self
    {
        $data = json_decode($body, true);
        $code = null;
        $detail = null;
        if (is_array($data)) {
            $code = isset($data['code']) && is_string($data['code']) ? $data['code'] : null;
            foreach (['message', 'error'] as $key) {
                if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                    $detail = $data[$key];
                    break;
                }
            }
        }
        return new self(sprintf('Trident API error (HTTP %d): %s', $status, $body), $status, $code, $detail);
    }

    public function status(): int
    {
        return $this->getCode();
    }

    /** No response at all: DNS, refused, timeout, TLS. */
    public function isUnreachable(): bool
    {
        return $this->getCode() === 0;
    }

    /**
     * The instance answered that the feature is not enabled in its
     * configuration — reflect, warmer, launch, a denoiser, discovery.
     *
     * The engine says so with a 404 carrying a `*_DISABLED` code, or a 503
     * whose message reads "… not enabled".
     */
    public function isFeatureDisabled(): bool
    {
        if ($this->engineCode !== null && str_ends_with($this->engineCode, '_DISABLED')) {
            return true;
        }
        return in_array($this->getCode(), [404, 503], true)
            && $this->engineMessage !== null
            && stripos($this->engineMessage, 'not enabled') !== false;
    }

    /** A short, operator-readable reason (never the token, never a stack). */
    public function reason(): string
    {
        if ($this->isUnreachable()) {
            return $this->getMessage();
        }
        return sprintf('HTTP %d%s', $this->getCode(), $this->engineMessage !== null ? ': ' . $this->engineMessage : '');
    }
}
