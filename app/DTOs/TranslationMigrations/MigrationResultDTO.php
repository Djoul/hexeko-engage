<?php

declare(strict_types=1);

namespace App\DTOs\TranslationMigrations;

class MigrationResultDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly int $migrationId,
        public readonly ?string $backupPath = null,
        public readonly ?string $error = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        int $migrationId,
        ?string $backupPath = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            migrationId: $migrationId,
            backupPath: $backupPath,
            error: null,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failure(
        int $migrationId,
        string $error,
        array $metadata = []
    ): self {
        return new self(
            success: false,
            migrationId: $migrationId,
            backupPath: null,
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'migration_id' => $this->migrationId,
            'backup_path' => $this->backupPath,
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }
}
