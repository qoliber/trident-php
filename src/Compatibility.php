<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident;

/**
 * Which Trident release line this library — and every integration built on it
 * — is made for.
 *
 * Trident packages are versioned in LOCKSTEP with the engine: a package's
 * MAJOR.MINOR is the engine's (library 1.8.x works with Trident 1.8), and only
 * the PATCH number moves on its own. Each engine minor release bumps
 * {@see self::TRIDENT}; a test in this repository fails when it lags behind the
 * engine, so the two cannot drift apart unnoticed.
 */
final class Compatibility
{
    /** Trident MAJOR.MINOR this library targets. */
    public const TRIDENT = '1.8';

    /**
     * A warning for an admin screen when the connected engine is on another
     * release line, or null when it matches (or its version is unreadable —
     * an unknown version is not a mismatch worth alarming anyone about).
     */
    public static function warning(string $engineVersion, string $target = self::TRIDENT): ?string
    {
        $line = self::line($engineVersion);
        if ($line === null || $line === $target) {
            return null;
        }
        $upgrade = version_compare($line . '.0', $target . '.0', '<')
            ? 'Upgrade Trident'
            : 'Upgrade this integration';

        return sprintf(
            'Trident %s is connected, but this integration is built for Trident %s.x. %s so both are on the same %s release line.',
            trim($engineVersion),
            $target,
            $upgrade,
            $target
        );
    }

    /** The MAJOR.MINOR of a version string ("1.8.2", "v1.9.0-rc.1"), or null. */
    public static function line(string $version): ?string
    {
        if (preg_match('/^v?(\d{1,3})\.(\d{1,3})(?:\.\d{1,5})?(?:[-+][0-9A-Za-z.-]{1,40})?$/', trim($version), $m) !== 1) {
            return null;
        }

        return $m[1] . '.' . $m[2];
    }
}
