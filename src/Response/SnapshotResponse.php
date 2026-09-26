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

class SnapshotResponse
{
    use CarriesRaw;

    public function __construct(
        public readonly bool $success,
        public readonly ?string $path,
        public readonly ?int $entryCount,
        public readonly ?int $fileSize,
        public readonly ?int $durationMs,
        public readonly ?bool $compressed,
        public readonly ?string $error
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return (new self(
            success: (bool) ($data['success'] ?? false),
            path: isset($data['path']) ? (string) $data['path'] : null,
            entryCount: isset($data['entry_count']) ? (int) $data['entry_count'] : null,
            fileSize: isset($data['file_size']) ? (int) $data['file_size'] : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            compressed: isset($data['compressed']) ? (bool) $data['compressed'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null
        ))->attachRaw($data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getEntryCount(): ?int
    {
        return $this->entryCount;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getFileSizeFormatted(): ?string
    {
        if ($this->fileSize === null) {
            return null;
        }

        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function isCompressed(): ?bool
    {
        return $this->compressed;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['success' => $this->success];

        if ($this->path !== null) {
            $data['path'] = $this->path;
        }
        if ($this->entryCount !== null) {
            $data['entry_count'] = $this->entryCount;
        }
        if ($this->fileSize !== null) {
            $data['file_size'] = $this->fileSize;
        }
        if ($this->durationMs !== null) {
            $data['duration_ms'] = $this->durationMs;
        }
        if ($this->compressed !== null) {
            $data['compressed'] = $this->compressed;
        }
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        return $data;
    }
}
