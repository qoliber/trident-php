<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Events;

/**
 * Represents a single SSE event from Trident
 */
class TridentEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly ?string $id = null
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Get a value from the event data
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if this is a request event
     */
    public function isRequest(): bool
    {
        return $this->type === 'request' || isset($this->data['url']);
    }

    /**
     * Check if this is a cache event
     */
    public function isCache(): bool
    {
        return in_array($this->type, ['hit', 'miss', 'eviction', 'cache'], true);
    }

    /**
     * Check if this is a backend event
     */
    public function isBackend(): bool
    {
        return $this->type === 'backend' || isset($this->data['backend']);
    }

    /**
     * Check if this is an error event
     */
    public function isError(): bool
    {
        return $this->type === 'error' || isset($this->data['error']);
    }

    /**
     * Check if this is a launch event
     */
    public function isLaunch(): bool
    {
        return str_starts_with($this->type, 'launch') || isset($this->data['launch_id']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'id' => $this->id,
        ];
    }
}
