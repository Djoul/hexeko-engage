<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Translation\EnvironmentConfigDTO;
use Illuminate\Support\Arr;

class EnvironmentService
{
    private const DEV_ENVIRONMENTS = ['dev', 'development'];

    private const PROD_ENVIRONMENTS = ['prod', 'production'];

    private string $currentEnvironment;

    /**
     * @var array<string, mixed>
     */
    private array $configOverrides;

    public function __construct(?string $environment = null, array $configOverrides = [])
    {
        $this->currentEnvironment = $this->normalizeEnvironment(
            $environment ?? app()->environment()
        );
        $this->configOverrides = $configOverrides;
    }

    public function useEnvironment(?string $environment = null): self
    {
        $this->currentEnvironment = $this->normalizeEnvironment(
            $environment ?? app()->environment()
        );

        return $this;
    }

    public function shouldAutoSync(): bool
    {
        if ($this->isEnvironment('local')) {
            return (bool) $this->getConfigValue('translations.auto_sync_local', false);
        }

        if ($this->isEnvironment('staging')) {
            return false;
        }

        return $this->isEnvironment([...self::DEV_ENVIRONMENTS, ...self::PROD_ENVIRONMENTS, 'staging', 'testing']);
    }

    public function shouldReconcile(): bool
    {
        if (! $this->getConfigValue('translations.reconciliation.enabled', true)) {
            return false;
        }

        return $this->isEnvironment([...self::DEV_ENVIRONMENTS, ...self::PROD_ENVIRONMENTS, 'staging', 'testing']);
    }

    public function canEditTranslations(): bool
    {
        if ($this->isEnvironment(['staging', 'testing'])) {
            return true;
        }

        if ($this->isEnvironment('local')) {
            return (bool) $this->getConfigValue('translations.allow_local_editing', false);
        }

        return false;
    }

    public function requiresManifest(): bool
    {
        if ($this->isEnvironment('staging')) {
            return true;
        }

        return $this->isEnvironment(self::PROD_ENVIRONMENTS);
    }

    public function shouldReconcileAfterSeed(): bool
    {
        if (! $this->getConfigValue('translations.reconciliation.auto_reconcile_after_seed', true)) {
            return false;
        }

        return $this->isEnvironment(self::DEV_ENVIRONMENTS);
    }

    public function shouldWarnOnMigrationWithoutReconciliation(): bool
    {
        return $this->isEnvironment(self::DEV_ENVIRONMENTS)
            && ! $this->getConfigValue('translations.reconciliation.enabled', true);
    }

    public function getConfig(): EnvironmentConfigDTO
    {
        return new EnvironmentConfigDTO(
            environment: $this->currentEnvironment,
            autoSyncEnabled: $this->shouldAutoSync(),
            reconciliationEnabled: $this->shouldReconcile(),
            manifestRequired: $this->requiresManifest() && $this->getConfigValue('translations.manifest_required', true),
            canEditTranslations: $this->canEditTranslations(),
            reconciliationCron: $this->getConfigValue('translations.reconciliation.cron', '0 * * * *')
        );
    }

    public function getCurrentEnvironment(): string
    {
        return $this->currentEnvironment;
    }

    /**
     * @param  array<int, string>|string  $environments
     */
    private function isEnvironment(array|string $environments): bool
    {
        $targets = (array) $environments;

        return in_array($this->currentEnvironment, array_map([$this, 'normalizeEnvironment'], $targets), true);
    }

    private function normalizeEnvironment(string $environment): string
    {
        return match (strtolower($environment)) {
            'production' => 'production',
            'prod' => 'production',
            'develop', 'development', 'dev' => 'dev',
            'staging' => 'staging',
            'local' => 'local',
            'testing', 'test' => 'testing',
            default => $environment,
        };
    }

    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        if (Arr::has($this->configOverrides, $key)) {
            return Arr::get($this->configOverrides, $key);
        }

        if (! function_exists('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
