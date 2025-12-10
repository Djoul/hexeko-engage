<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

class EnvironmentConfigDTO
{
    public function __construct(
        public readonly string $environment,
        public readonly bool $autoSyncEnabled,
        public readonly bool $reconciliationEnabled,
        public readonly bool $manifestRequired,
        public readonly bool $canEditTranslations,
        public readonly string $reconciliationCron,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            environment: (string) $data['environment'],
            autoSyncEnabled: (bool) $data['autoSyncEnabled'],
            reconciliationEnabled: (bool) $data['reconciliationEnabled'],
            manifestRequired: (bool) $data['manifestRequired'],
            canEditTranslations: (bool) $data['canEditTranslations'],
            reconciliationCron: (string) $data['reconciliationCron'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'autoSyncEnabled' => $this->autoSyncEnabled,
            'reconciliationEnabled' => $this->reconciliationEnabled,
            'manifestRequired' => $this->manifestRequired,
            'canEditTranslations' => $this->canEditTranslations,
            'reconciliationCron' => $this->reconciliationCron,
        ];
    }
}
