<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;


/**
 * Turns the library's typed answers into plain arrays for the administration.
 */
final class Export
{
    public static function value(mixed $value): mixed
    {
        // Typed responses: their typed fields. Untyped answers (Payload) come
        // only from an instance verified to be Trident (AdminService::verified).
        if (is_object($value) && method_exists($value, 'toArray')) {
            return self::value($value->toArray());
        }
        if (is_object($value) && method_exists($value, 'raw')) {
            return self::value($value->raw());
        }
        if (is_array($value)) {
            return array_map(self::value(...), $value);
        }
        if (is_object($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<InstanceResult<mixed>> $results
     *
     * @return list<array{instance: string, ok: bool, unreachable: bool, disabled: bool, error: ?string, data: mixed}>
     */
    public static function results(array $results): array
    {
        return array_map(static fn (InstanceResult $r): array => [
            'instance' => $r->name(),
            'ok' => $r->isOk(),
            'unreachable' => $r->isUnreachable(),
            'disabled' => $r->isFeatureDisabled(),
            'error' => $r->isOk() ? null : $r->reason(),
            'data' => $r->isOk() ? self::value($r->value) : null,
        ], $results);
    }
}
