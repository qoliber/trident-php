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

use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Exception\TridentException;

/**
 * One instance's answer to a fleet call: the value, or why there is none.
 *
 * @template-covariant T
 */
final class InstanceResult
{
    /**
     * @param T|null $value
     */
    private function __construct(
        public readonly Instance $instance,
        public readonly mixed $value,
        public readonly ?TridentException $error
    ) {
    }

    /**
     * @template V
     * @param V $value
     * @return self<V>
     */
    public static function ok(Instance $instance, mixed $value): self
    {
        return new self($instance, $value, null);
    }

    /**
     * @return self<null>
     */
    public static function failed(Instance $instance, TridentException $error): self
    {
        return new self($instance, null, $error);
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    public function name(): string
    {
        return $this->instance->name;
    }

    /** No response at all from the instance. */
    public function isUnreachable(): bool
    {
        return $this->error instanceof ApiError && $this->error->isUnreachable();
    }

    /** The instance answered that the feature is not enabled there. */
    public function isFeatureDisabled(): bool
    {
        return $this->error instanceof ApiError && $this->error->isFeatureDisabled();
    }

    /** Why there is no value; empty when there is one. */
    public function reason(): string
    {
        if ($this->error === null) {
            return '';
        }
        return $this->error instanceof ApiError ? $this->error->reason() : $this->error->getMessage();
    }
}
