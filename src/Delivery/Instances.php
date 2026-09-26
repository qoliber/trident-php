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
 * X03 — parses a platform's list of Trident instances.
 *
 * A platform keeps the list in deployment configuration (Magento env.php,
 * WordPress wp-config.php, Shopware/Symfony parameters) where it takes
 * precedence over the single URL from the admin UI: how many edges there are
 * belongs to deployment, not to whoever can open the settings page.
 *
 *     ['edge-1' => ['api_url' => 'http://10.0.0.11:9301'],
 *      'edge-2' => ['api_url' => 'http://10.0.0.12:9301', 'api_token' => '…']]
 *
 * An entry without its own token uses the default token.
 */
final class Instances
{
    /** Name of the single instance configured without a list. */
    public const DEFAULT_NAME = 'default';

    /**
     * Instance names end up in a case-sensitive column: "Edge-1" and "edge-1"
     * are two instances, and a name the column cannot hold is refused here
     * rather than truncated there.
     */
    private const NAME_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

    /**
     * @param mixed  $configured   The list, or null/[] when there is none.
     * @param string $defaultUrl   The admin-UI URL, used when there is no list.
     * @param string $defaultToken The admin-UI token, also the fallback per entry.
     * @return array{0: list<Instance>, 1: list<string>} Instances, and why
     *         configured entries were skipped.
     */
    public static function parse(mixed $configured, string $defaultUrl, string $defaultToken): array
    {
        if (!is_array($configured) || $configured === []) {
            $defaultUrl = trim($defaultUrl);
            if ($defaultUrl === '') {
                return [[], []];
            }
            if (!self::isHttpUrl($defaultUrl)) {
                return [[], [sprintf('API URL "%s" is not an http(s) URL', $defaultUrl)]];
            }
            return [[new Instance(self::DEFAULT_NAME, rtrim($defaultUrl, '/'), $defaultToken)], []];
        }

        $instances = [];
        $errors = [];
        $seen = [];
        $position = 0;
        foreach ($configured as $key => $entry) {
            ++$position;
            $name = is_string($key) && $key !== '' ? $key : 'instance-' . $position;
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                $errors[] = sprintf('%s: name must be 1-64 characters of A-Z a-z 0-9 . _ -', $name);
                continue;
            }
            if (!is_array($entry)) {
                $errors[] = sprintf('%s: expected an array with api_url', $name);
                continue;
            }
            $url = trim((string) ($entry['api_url'] ?? ''));
            if ($url === '') {
                $errors[] = sprintf('%s: no api_url', $name);
                continue;
            }
            if (!self::isHttpUrl($url)) {
                $errors[] = sprintf('%s: api_url "%s" is not an http(s) URL', $name, $url);
                continue;
            }
            if (isset($seen[$name])) {
                $errors[] = sprintf('%s: defined twice', $name);
                continue;
            }
            $seen[$name] = true;
            $token = isset($entry['api_token']) && (string) $entry['api_token'] !== ''
                ? (string) $entry['api_token']
                : $defaultToken;
            $instances[] = new Instance($name, rtrim($url, '/'), $token);
        }
        return [$instances, $errors];
    }

    private static function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        return ($scheme === 'http' || $scheme === 'https') && $host !== '';
    }
}
