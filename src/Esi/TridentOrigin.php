<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Esi;

/**
 * Whether a request reached the application through Trident.
 *
 * Trident adds no header an application could trust (a client can send any
 * header), so the check is on the connection: the immediate peer
 * (`REMOTE_ADDR` as the web server saw it) must be a Trident address. A
 * platform uses it to emit ESI markup only for requests that an ESI processor
 * will see — an administrator hitting the backend port directly gets plain
 * inline markup.
 */
final class TridentOrigin
{
    /**
     * @param list<string> $cidrs Addresses or CIDR ranges of the Trident instances (IPv4/IPv6).
     */
    public static function matches(string $remoteAddr, array $cidrs): bool
    {
        $addr = self::packed(trim($remoteAddr));
        if ($addr === null) {
            return false;
        }
        foreach ($cidrs as $cidr) {
            if (self::inRange($addr, trim((string) $cidr))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Packed address; an IPv4-mapped IPv6 address (`::ffff:127.0.0.1`, what a
     * dual-stack socket reports for an IPv4 peer) as its IPv4 form.
     */
    private static function packed(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return null;
        }
        if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return substr($bin, 12);
        }
        return $bin;
    }

    /**
     * A malformed range (`10.0.0.0/`, `/abc`, `/33`) matches nothing — never
     * everything.
     */
    private static function inRange(string $addr, string $cidr): bool
    {
        $network = $cidr;
        $bits = null;
        $slash = strpos($cidr, '/');
        if ($slash !== false) {
            $network = substr($cidr, 0, $slash);
            $suffix = substr($cidr, $slash + 1);
            if (preg_match('/^\d{1,3}$/', $suffix) !== 1) {
                return false;
            }
            $bits = (int) $suffix;
        }
        $net = @inet_pton($network);
        if ($network === '' || $net === false) {
            return false;
        }
        $width = strlen($net) * 8;
        if ($bits === null) {
            $bits = $width;
        }
        if ($bits > $width) {
            return false;
        }
        // An IPv4 peer against a mapped range written in IPv6 form.
        if (strlen($net) === 16 && strlen($addr) === 4) {
            $addr = str_repeat("\0", 10) . "\xff\xff" . $addr;
        }
        if (strlen($net) !== strlen($addr)) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (substr($addr, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($addr[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
