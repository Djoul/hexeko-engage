<?php

declare(strict_types=1);

namespace App\DTOs\TranslationMigrations;

class RollbackMigrationDTO
{
    public function __construct(
        public readonly int $migrationId,
        public readonly string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            migrationId: is_int($data['migration_id']) ? $data['migration_id'] : 0,
            reason: is_string($data['reason']) ? $data['reason'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'migration_id' => $this->migrationId,
            'reason' => $this->reason,
        ];
    }
}
