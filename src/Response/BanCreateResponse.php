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

class BanCreateResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $id = null,
        public readonly ?string $pattern = null,
        public readonly ?string $expires = null,
        public readonly ?string $message = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: isset($data['id']) || ($data['success'] ?? false),
            id: isset($data['id']) ? (string) $data['id'] : null,
            pattern: $data['pattern'] ?? null,
            expires: $data['expires'] ?? null,
            message: $data['message'] ?? null
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getBanId(): ?string
    {
        return $this->id;
    }
}
