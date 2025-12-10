<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class RestoreResultDTO
{
    /**
     * @param  array<string, mixed>  $languageBreakdown
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $interface,
        public readonly string $backupFile,
        public readonly string $restoredVersion,
        public readonly int $keysRestored,
        public readonly array $languageBreakdown,
        public readonly Carbon $restoredAt,
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            success: (bool) $data['success'],
            interface: (string) $data['interface'],
            backupFile: (string) $data['backupFile'],
            restoredVersion: (string) $data['restoredVersion'],
            keysRestored: (int) $data['keysRestored'],
            languageBreakdown: (array) $data['languageBreakdown'],
            restoredAt: Carbon::parse((string) $data['restoredAt']),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'interface' => $this->interface,
            'backupFile' => $this->backupFile,
            'restoredVersion' => $this->restoredVersion,
            'keysRestored' => $this->keysRestored,
            'languageBreakdown' => $this->languageBreakdown,
            'restoredAt' => $this->restoredAt,
            'error' => $this->error,
        ];
    }
}
