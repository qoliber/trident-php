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

class DiscoveryRefreshResponse
{
    /**
     * @param array<string> $ips
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $name,
        public readonly array $ips,
        public readonly ?string $message = null,
        public readonly ?string $error = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool) ($data['success'] ?? true),
            name: (string) ($data['name'] ?? ''),
            ips: (array) ($data['ips'] ?? $data['resolved_ips'] ?? []),
            message: isset($data['message']) ? (string) $data['message'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array<string>
     */
    public function getIps(): array
    {
        return $this->ips;
    }

    public function getIpCount(): int
    {
        return count($this->ips);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'name' => $this->name,
            'ips' => $this->ips,
            'message' => $this->message,
            'error' => $this->error,
        ];
    }
}
