<?php

declare(strict_types=1);

namespace App\DTOs\TranslationMigrations;

class ApplyMigrationDTO
{
    public function __construct(
        public readonly int $migrationId,
        public readonly bool $createBackup = true,
        public readonly bool $validateChecksum = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            migrationId: is_int($data['migration_id']) ? $data['migration_id'] : 0,
            createBackup: array_key_exists('create_backup', $data) && is_bool($data['create_backup']) ? $data['create_backup'] : true,
            validateChecksum: array_key_exists('validate_checksum', $data) && is_bool($data['validate_checksum']) ? $data['validate_checksum'] : true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'migration_id' => $this->migrationId,
            'create_backup' => $this->createBackup,
            'validate_checksum' => $this->validateChecksum,
        ];
    }
}
