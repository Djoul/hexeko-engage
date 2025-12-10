<?php

namespace Database\Seeders;

use App\Integrations\InternalCommunication\Database\Seeders\TagSeeder;
use App\Integrations\Survey\Database\seeders\DemoSeeder;
use App\Models\Permission;
use App\Models\Role;
use App\Services\EnvironmentService;
use App\Services\SlackService;
use DateTimeInterface;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\Activity;
use Throwable;

class DatabaseSeeder extends BaseSeeder
{
    private const SLACK_CHANNEL = '#up-engage-tech';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $env = app()->environment();
        // Truncate permission and role tables including pivot tables
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();

        Role::truncate();
        Permission::truncate();

        // Base seeders (always run)
        // region roles and permissions
        $this->call(TeamsTableSeeder::class);

        // Dynamic permission seeding from enum
        $this->call(DynamicPermissionSeeder::class);

        $this->call(RolesTableSeeder::class);

        // Dynamic role-permission associations from enum
        $this->call(DynamicRolePermissionSeeder::class);
        // endregion

        $this->call(DivisionSeeder::class);

        // Environment-based data seeding

        $this->call(ProductionFinancersTableSeeder::class);
        $this->call(ProductionUsersTableSeeder::class);
        $this->call(ProductionFinancerUserTableSeeder::class);
        $this->call(ProductionModelHasRolesTableSeeder::class);

        Activity::disableLogging();

        if (in_array($env, ['local', 'dev', 'staging'])) {
            $this->call(StagingFinancersTableSeeder::class);
            $this->call(StagingUsersTableSeeder::class);
            $this->call(StagingFinancerUserTableSeeder::class);

            // Com RH
            $this->call(StagingIntCommunicationRhArticlesTableSeeder::class);
            $this->call(StagingIntCommunicationRhArticleTranslationsTableSeeder::class);
            $this->call(StagingIntCommunicationRhArticleVersionsTableSeeder::class);
            $this->call(StagingLlmRequestsTableSeeder::class);

            // demo Entities
            $this->call(StagingDemoEntitiesTableSeeder::class);

            // Tools RH
            $this->call(StagingIntOutilsRhLinksTableSeeder::class);
            $this->call(LinksDemoEntitiesSeeder::class);

        }

        if (in_array($env, ['local', 'dev'])) {
            $this->call(DevFinancersTableSeeder::class);
            $this->call(DevUsersTableSeeder::class);
            $this->call(DevFinancerUserTableSeeder::class);
            $this->call(InvitedUsersSeeder::class);
            // Com RH
            $this->call(DevIntCommunicationRhArticlesTableSeeder::class);
            $this->call(DevIntCommunicationRhArticleTranslationsTableSeeder::class);
            $this->call(DevIntCommunicationRhArticleVersionsTableSeeder::class);
            $this->call(DevLlmRequestsTableSeeder::class);
            // demo Entities
            $this->call(DevDemoEntitiesTableSeeder::class);
            // Tools RH
            $this->call(DevIntOutilsRhLinksTableSeeder::class);

            $this->call(LinksDemoEntitiesSeeder::class);
            // Departments
            $this->call(DepartmentDemoSeeder::class);
            // Sites
            $this->call(SiteDemoSeeder::class);
            // Contract types
            $this->call(ContractTypeDemoSeeder::class);
            // Tags
            $this->call(TagDemoSeeder::class);
            // Job titles
            $this->call(JobTitleDemoSeeder::class);
            // Job levels
            $this->call(JobLevelDemoSeeder::class);
            // Work modes
            $this->call(WorkModeDemoSeeder::class);
        }

        if ($env == 'local') {
            // Survey
            $this->call(DemoSeeder::class);
        }

        $this->call(AssignRolesFromPivotSeeder::class);

        // Sync language from user locale to financer_user pivot
        $this->call(FinancerUserLanguageSeeder::class);

        // Additional seeders
        $this->call(FinancerCreditSeeder::class);

        // module && integrations
        $this->call(ModuleSeeder::class);
        $this->call(IntegrationSeeder::class);
        $this->call(IntegrationResourcesCountSeeder::class);

        //
        $this->call(TagSeeder::class);
        $this->call(TranslationKeysTableSeeder::class);
        $this->call(TranslationValuesTableSeeder::class);

        $this->reconcileTranslationsIfNeeded();

        Activity::enableLogging();
    }

    private function reconcileTranslationsIfNeeded(): void
    {
        /** @var EnvironmentService $environmentService */
        $environmentService = app(EnvironmentService::class);

        if (! $environmentService->shouldReconcileAfterSeed()) {
            $this->notify(
                '⏭️  Skipping translation reconciliation (environment not eligible)',
                'Seed terminé - réconciliation non requise'
            );

            return;
        }

        if (! config('translations.reconciliation.auto_reconcile_after_seed', true)) {
            $this->notify(
                '⏭️  Skipping translation reconciliation (disabled in config)',
                'Seed terminé - réconciliation désactivée via configuration'
            );

            return;
        }

        $throttleSeconds = (int) config('translations.reconciliation.throttle_seconds', 300);
        $cacheKey = 'last_reconciliation_seeder';

        if (Cache::has($cacheKey)) {
            $lastReconciliation = Cache::get($cacheKey);
            $elapsed = $lastReconciliation instanceof DateTimeInterface
                ? Date::now()->diffInSeconds($lastReconciliation)
                : 0;

            $this->notify(
                "⏭️  Skipping reconciliation (already done {$elapsed}s ago, throttle: {$throttleSeconds}s)",
                'Throttle actif: réconciliation déjà exécutée récemment',
                true
            );

            return;
        }

        $this->notify('🔄 Starting translation reconciliation from seeder...');

        try {
            $exitCode = Artisan::call('translations:auto-reconcile', [
                '--all' => true,
                '--force' => true,
            ]);

            $output = trim((string) Artisan::output());

            if ($exitCode === Command::SUCCESS) {
                $this->notify(
                    '✅ Translation reconciliation completed successfully',
                    'Seed terminé - réconciliation effectuée avec succès',
                    true
                );
                Cache::put($cacheKey, Date::now(), $throttleSeconds);
            } else {
                $this->notify(
                    '⚠️  Translation reconciliation completed with warnings',
                    'Seed terminé - réconciliation terminée avec avertissements',
                    true
                );
            }

            if ($output !== '') {
                $this->notify($output);
            }
        } catch (Throwable $e) {
            $message = '❌ Translation reconciliation failed: '.$e->getMessage();
            $this->notify($message, $message, true);

            Log::error('Translation reconciliation failed during seed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function notify(string $consoleMessage, ?string $fallbackSlackMessage = null, bool $forceSlack = false): void
    {
        $commandAvailable = $this->command instanceof Command;

        if ($commandAvailable) {
            $this->command->line($consoleMessage);
        }

        // Send to Slack if forced OR if no console is available (fallback)
        $shouldNotifySlack = $forceSlack || ! $commandAvailable;

        if ($shouldNotifySlack) {
            /** @var SlackService $slackService */
            $slackService = app(SlackService::class);

            $slackService->sendToPublicChannel(
                $fallbackSlackMessage ?? $consoleMessage,
                self::SLACK_CHANNEL
            );
        }
    }
}
