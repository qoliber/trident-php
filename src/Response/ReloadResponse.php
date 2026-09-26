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

class ReloadResponse
{
    use CarriesRaw;

    public function __construct(
        public readonly bool $success,
        public readonly ?string $status = null,
        public readonly ?string $config = null,
        public readonly ?string $message = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        return (new self(
            success: in_array($status, ['reloaded', 'ok'], true) || ($data['success'] ?? false),
            status: $status,
            config: $data['config'] ?? null,
            message: $data['message'] ?? null
        ))->attachRaw($data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getConfigPath(): ?string
    {
        return $this->config;
    }
}
