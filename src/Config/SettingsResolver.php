<?php

declare(strict_types=1);

namespace Qoliber\Trident\Config;

use Qoliber\Trident\Delivery\ApiHostAllowlist;
use Qoliber\Trident\Delivery\Instances;

/**
 * Builds {@see Settings} from the two places an operator configures Trident.
 *
 * The DEPLOYMENT config wins over the admin setting: `TRIDENT_INSTANCES` (a JSON object `{"name": {"api_url": …,
 * "api_token": …}}`) or `TRIDENT_API_URL`, with `TRIDENT_API_TOKEN` as the
 * token of an instance that names none. The admin screen's single URL and
 * token are the fallback for a one-server shop.
 *
 * A token from the environment goes ONLY to URLs from the environment; the
 * admin URL gets only the admin token, which is sealed to that URL. Someone
 * who can edit the shop's settings can therefore never make the deployment's
 * token travel to their host.
 *
 * Pure: the caller passes the environment and the stored settings in.
 */
final class SettingsResolver
{
    /**
     * @param array<string, mixed> $env    TRIDENT_* variables.
     * @param array<string, mixed> $stored The admin settings: api_url, api_token (opened, or null), token_error, purge_mode.
     */
    public static function resolve(array $env, array $stored): Settings
    {
        $envToken = self::str($env['TRIDENT_API_TOKEN'] ?? null) ?? '';
        $errors = [];
        $configured = null;
        $source = 'admin';

        $json = self::str($env['TRIDENT_INSTANCES'] ?? null);
        if ($json !== null) {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && $decoded !== []) {
                $configured = $decoded;
                $source = 'environment (TRIDENT_INSTANCES)';
            } else {
                $errors[] = 'TRIDENT_INSTANCES is not a JSON object of instances; using the admin setting';
            }
        }
        $envUrl = self::str($env['TRIDENT_API_URL'] ?? null);
        if ($configured !== null) {
            [$instances, $parseErrors] = Instances::parse($configured, '', $envToken);
        } elseif ($envUrl !== null) {
            $source = 'environment (TRIDENT_API_URL)';
            [$instances, $parseErrors] = Instances::parse(null, $envUrl, $envToken);
        } elseif (($stored['read_failed'] ?? false) === true) {
            // The admin settings could not be read: which instance they name is
            // unknown now, but a purge must not be lost. Recorded for the admin
            // instance's name ("default"); delivered once the settings read.
            $instances = [];
            $parseErrors = [Settings::READ_FAILED];
        } else {
            [$instances, $parseErrors] = Instances::parse(null, self::str($stored['api_url'] ?? null) ?? '', self::str($stored['api_token'] ?? null) ?? '');
            // Only when the admin URL is the one in use: a shop configured in
            // its environment (a staging copy of the database) does not care.
            if (is_string($stored['token_error'] ?? null)) {
                $errors[] = $stored['token_error'];
            }
        }
        $errors = array_merge($errors, $parseErrors);

        [$instances, $allowErrors] = ApiHostAllowlist::filter($instances, self::str($env['TRIDENT_ALLOWED_API_HOSTS'] ?? null) ?? '', 'TRIDENT_ALLOWED_API_HOSTS');
        $errors = array_merge($errors, $allowErrors);

        $mode = self::str($env['TRIDENT_PURGE_MODE'] ?? null) ?? self::str($stored['purge_mode'] ?? null) ?? Settings::MODE_SOFT;

        return new Settings(
            instances: $instances,
            errors: array_values($errors),
            source: $instances === [] ? 'none' : $source,
            mode: $mode === Settings::MODE_HARD ? Settings::MODE_HARD : Settings::MODE_SOFT,
            tagPrefix: self::str($env['TRIDENT_TAG_PREFIX'] ?? null) ?? '',
            readFailed: ($stored['read_failed'] ?? false) === true,
            debugHeaders: in_array(strtolower((string) (self::str($env['TRIDENT_DEBUG_HEADERS'] ?? null) ?? '')), ['1', 'true', 'on', 'yes'], true),
        );
    }

    private static function str(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
