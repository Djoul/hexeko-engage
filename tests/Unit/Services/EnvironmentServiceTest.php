<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EnvironmentService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migration')]
#[Group('translation-protection')]
class EnvironmentServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeService(string $environment, array $overrides = []): EnvironmentService
    {
        $baseConfig = [
            'translations.auto_sync_local' => false,
            'translations.allow_local_editing' => false,
            'translations.reconciliation.enabled' => true,
            'translations.reconciliation.auto_reconcile_after_seed' => true,
            'translations.reconciliation.throttle_seconds' => 300,
            'translations.reconciliation.cron' => '0 * * * *',
            'translations.manifest_required' => true,
        ];

        return new EnvironmentService($environment, $overrides + $baseConfig);
    }

    #[Test]
    public function it_determines_auto_sync_correctly_for_each_environment(): void
    {
        $this->assertFalse($this->makeService('local')->shouldAutoSync());
        $this->assertTrue($this->makeService('local', ['translations.auto_sync_local' => true])->shouldAutoSync());

        foreach (['dev', 'development'] as $environment) {
            $this->assertTrue($this->makeService($environment)->shouldAutoSync());
        }

        foreach (['prod', 'production'] as $environment) {
            $this->assertTrue($this->makeService($environment)->shouldAutoSync());
        }

        $this->assertFalse($this->makeService('staging')->shouldAutoSync());
        $this->assertTrue($this->makeService('testing')->shouldAutoSync());
    }

    #[Test]
    public function it_determines_reconciliation_correctly_for_each_environment(): void
    {
        $this->assertFalse($this->makeService('local')->shouldReconcile());
        $this->assertTrue($this->makeService('dev')->shouldReconcile());
        $this->assertTrue($this->makeService('production')->shouldReconcile());
        $this->assertTrue($this->makeService('testing')->shouldReconcile());
        $this->assertTrue($this->makeService('staging')->shouldReconcile());

        $this->assertFalse($this->makeService('dev', ['translations.reconciliation.enabled' => false])->shouldReconcile());
    }

    #[Test]
    public function it_determines_translation_editing_permissions_correctly(): void
    {
        $this->assertFalse($this->makeService('local')->canEditTranslations());
        $this->assertFalse($this->makeService('dev')->canEditTranslations());
        $this->assertFalse($this->makeService('production')->canEditTranslations());

        $this->assertTrue($this->makeService('staging')->canEditTranslations());
        $this->assertTrue($this->makeService('testing')->canEditTranslations());

        $this->assertTrue($this->makeService('local', ['translations.allow_local_editing' => true])->canEditTranslations());
    }

    #[Test]
    public function it_determines_manifest_requirement_correctly(): void
    {
        $this->assertFalse($this->makeService('local')->requiresManifest());
        $this->assertFalse($this->makeService('dev')->requiresManifest());
        $this->assertTrue($this->makeService('staging')->requiresManifest());
        $this->assertTrue($this->makeService('prod')->requiresManifest());
    }

    #[Test]
    public function it_reconciles_after_seed_only_on_dev(): void
    {
        $this->assertFalse($this->makeService('local')->shouldReconcileAfterSeed());
        $this->assertTrue($this->makeService('dev')->shouldReconcileAfterSeed());
        $this->assertFalse($this->makeService('staging')->shouldReconcileAfterSeed());
        $this->assertFalse($this->makeService('production')->shouldReconcileAfterSeed());

        $this->assertFalse($this->makeService('dev', ['translations.reconciliation.auto_reconcile_after_seed' => false])->shouldReconcileAfterSeed());
    }

    #[Test]
    public function it_warns_on_migration_without_reconciliation_in_dev_only(): void
    {
        $this->assertFalse($this->makeService('dev')->shouldWarnOnMigrationWithoutReconciliation());
        $this->assertTrue($this->makeService('dev', ['translations.reconciliation.enabled' => false])->shouldWarnOnMigrationWithoutReconciliation());
        $this->assertFalse($this->makeService('staging', ['translations.reconciliation.enabled' => false])->shouldWarnOnMigrationWithoutReconciliation());
    }

    #[Test]
    public function it_returns_complete_environment_configuration(): void
    {
        $overrides = [
            'translations.reconciliation.cron' => '0 */2 * * *',
            'translations.reconciliation.throttle_seconds' => 600,
        ];

        $config = $this->makeService('staging', $overrides)->getConfig();

        $this->assertSame('staging', $config->environment);
        $this->assertFalse($config->autoSyncEnabled);
        $this->assertTrue($config->reconciliationEnabled);
        $this->assertTrue($config->manifestRequired);
        $this->assertTrue($config->canEditTranslations);
        $this->assertSame('0 */2 * * *', $config->reconciliationCron);
    }

    #[Test]
    public function it_returns_current_environment(): void
    {
        $this->assertSame('dev', $this->makeService('dev')->getCurrentEnvironment());
    }
}
