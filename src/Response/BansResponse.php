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

class BansResponse
{
    /**
     * @param array<array{ban_type: string, pattern: string, affected: int, created_at: string, age_secs: int, active: bool}> $bans
     */
    public function __construct(
        public readonly int $total,
        public readonly int $active,
        public readonly array $bans
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            active: (int) ($data['active'] ?? 0),
            bans: $data['bans'] ?? []
        );
    }

    public function hasActiveBans(): bool
    {
        return $this->active > 0;
    }
}
