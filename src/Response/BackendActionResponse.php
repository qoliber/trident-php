<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Response;

class BackendActionResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $backend = null,
        public readonly ?string $action = null,
        public readonly ?string $message = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: ($data['success'] ?? false) || ($data['status'] ?? '') === 'ok',
            backend: $data['backend'] ?? null,
            action: $data['action'] ?? null,
            message: $data['message'] ?? null
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
