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

/**
 * A read-only view of an admin endpoint's JSON, for the endpoints whose shape
 * is wide or still growing (warmer, reflect, denoisers, coverage, explain,
 * variants, ESI fragments). Accessors take a dotted path and never throw: a
 * missing or mistyped value is the default.
 */
final class Payload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }

    public function has(string $path): bool
    {
        return $this->get($path, $this) !== $this;
    }

    public function string(string $path, string $default = ''): string
    {
        $value = $this->get($path);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $path, int $default = 0): int
    {
        $value = $this->get($path);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $path, float $default = 0.0): float
    {
        $value = $this->get($path);
        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $path, bool $default = false): bool
    {
        $value = $this->get($path);
        return is_bool($value) ? $value : $default;
    }

    /**
     * A list of rows (arrays); anything that is not one is dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $path): array
    {
        $value = $this->get($path);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
