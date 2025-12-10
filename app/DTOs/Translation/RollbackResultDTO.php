<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class RollbackResultDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly string $interface,
        public readonly string $restoredVersion,
        public readonly string $previousVersion,
        public readonly string $backupPath,
        public readonly int $filesAffected,
        public readonly Carbon $rolledBackAt,
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
            restoredVersion: (string) $data['restoredVersion'],
            previousVersion: (string) $data['previousVersion'],
            backupPath: (string) $data['backupPath'],
            filesAffected: (int) $data['filesAffected'],
            rolledBackAt: Carbon::parse((string) $data['rolledBackAt']),
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
            'restoredVersion' => $this->restoredVersion,
            'previousVersion' => $this->previousVersion,
            'backupPath' => $this->backupPath,
            'filesAffected' => $this->filesAffected,
            'rolledBackAt' => $this->rolledBackAt,
            'error' => $this->error,
        ];
    }
}
