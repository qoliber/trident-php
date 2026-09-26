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

class RulesResponse
{
    use CarriesRaw;

    /**
     * @param array<array{name: string, priority: int, enabled: bool, evaluations: int, matches: int}> $request
     * @param array<array{name: string, priority: int, enabled: bool, evaluations: int, matches: int}> $response
     */
    public function __construct(
        public readonly int $requestRules = 0,
        public readonly int $responseRules = 0,
        public readonly array $request = [],
        public readonly array $response = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            requestRules: (int) ($data['request_rules'] ?? 0),
            responseRules: (int) ($data['response_rules'] ?? 0),
            request: $data['request'] ?? [],
            response: $data['response'] ?? []
        ))->attachRaw($data);
    }

    public function getTotalRules(): int
    {
        return $this->requestRules + $this->responseRules;
    }

    public function getTotalEvaluations(): int
    {
        $total = 0;
        foreach ($this->request as $rule) {
            $total += $rule['evaluations'] ?? 0;
        }
        foreach ($this->response as $rule) {
            $total += $rule['evaluations'] ?? 0;
        }
        return $total;
    }

    public function getTotalMatches(): int
    {
        $total = 0;
        foreach ($this->request as $rule) {
            $total += $rule['matches'] ?? 0;
        }
        foreach ($this->response as $rule) {
            $total += $rule['matches'] ?? 0;
        }
        return $total;
    }
}
