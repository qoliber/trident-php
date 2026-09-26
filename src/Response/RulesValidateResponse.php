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

class RulesValidateResponse
{
    public function __construct(
        public readonly bool $valid,
        public readonly int $requestRules = 0,
        public readonly int $responseRules = 0,
        public readonly ?string $error = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valid: $data['valid'] ?? false,
            requestRules: (int) ($data['request_rules'] ?? 0),
            responseRules: (int) ($data['response_rules'] ?? 0),
            error: $data['error'] ?? null
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getTotalRules(): int
    {
        return $this->requestRules + $this->responseRules;
    }
}
