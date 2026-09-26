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

class TopUrlsResponse
{
    use CarriesRaw;

    /**
     * @param array<array{path: string, requests: int, bytes: int, hits: int, misses: int, errors: int, hit_ratio: float, avg_duration_ms: float}> $urls
     * @param array{path: string, requests: int, bytes: int, hits: int, misses: int, errors: int, hit_ratio: float, avg_duration_ms: float}|null $totals
     */
    public function __construct(
        public readonly int $windowSecs,
        public readonly int $trackedUrls,
        public readonly string $sort,
        public readonly array $urls,
        public readonly ?array $totals = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            windowSecs: (int) ($data['window_secs'] ?? 0),
            trackedUrls: (int) ($data['tracked_urls'] ?? 0),
            sort: (string) ($data['sort'] ?? 'requests'),
            urls: $data['urls'] ?? [],
            totals: $data['totals'] ?? null
        ))->attachRaw($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'window_secs' => $this->windowSecs,
            'tracked_urls' => $this->trackedUrls,
            'sort' => $this->sort,
            'urls' => $this->urls,
            'totals' => $this->totals,
        ];
    }
}
