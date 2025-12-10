<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class BackupInfoDTO
{
    public function __construct(
        public readonly string $filename,
        public readonly string $path,
        public readonly string $version,
        public readonly Carbon $createdAt,
        public readonly int $size,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            filename: (string) $data['filename'],
            path: (string) $data['path'],
            version: (string) $data['version'],
            createdAt: Carbon::parse((string) $data['createdAt']),
            size: (int) $data['size'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'path' => $this->path,
            'version' => $this->version,
            'createdAt' => $this->createdAt,
            'size' => $this->size,
        ];
    }
}
