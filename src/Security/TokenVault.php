<?php

declare(strict_types=1);

namespace Qoliber\Trident\Security;

/**
 * An administration-stored Trident API token, encrypted at rest and BOUND TO
 * ITS URL (1.7.0).
 *
 * XChaCha20-Poly1305 (libsodium) with the normalised API URL as associated
 * data: the ciphertext only opens for the URL it was saved with. Someone who
 * can edit a shop's settings but points the URL at their own host gets
 * nothing — the token does not decrypt for that URL and is not sent. Changing
 * the URL therefore requires re-entering the token.
 *
 * Key: HKDF-SHA256 of the application secret (a platform passes e.g. Symfony's
 * APP_SECRET, or an explicit key from its own environment variable), with an
 * `$info` label per platform, so a database dump alone does not reveal the
 * token and a value sealed by one integration does not open in another.
 */
final class TokenVault
{
    public const PREFIX = 'tc1:';
    public const DEFAULT_INFO = 'qoliber/trident:api-token:v1';

    private readonly string $key;

    public function __construct(#[\SensitiveParameter] string $secret, string $info = self::DEFAULT_INFO)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('An application secret is required to store the Trident token');
        }
        $this->key = hash_hkdf('sha256', $secret, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, $info);
    }

    /**
     * var_dump()/print_r() never show the key.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['key' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('A TokenVault holds a key and is not serialisable');
    }

    public static function isSealed(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public function seal(#[\SensitiveParameter] string $token, string $apiUrl): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($token, self::normaliseUrl($apiUrl), $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * The token, or null when it was sealed for another URL or another key.
     */
    public function open(#[\SensitiveParameter] string $sealed, string $apiUrl): ?string
    {
        if (!self::isSealed($sealed)) {
            return null;
        }
        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);
        $n = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $n) {
            return null;
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, $n), self::normaliseUrl($apiUrl), substr($raw, 0, $n), $this->key);

        return is_string($plain) ? $plain : null;
    }

    /**
     * The associated data: scheme, host, port and path — what decides where the
     * token is sent. Lowercased; a trailing slash does not count.
     */
    public static function normaliseUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '/');
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . rtrim($parts['path'] ?? '', '/');
    }
}
