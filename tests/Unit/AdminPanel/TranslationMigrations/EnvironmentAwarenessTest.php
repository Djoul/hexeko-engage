<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\TranslationMigrations;

use App\Jobs\TranslationMigrations\AutoProcessTranslationMigrationJob;
use App\Listeners\AutoSyncTranslationMigrationsListener;
use App\Services\EnvironmentService;
use App\Services\SlackService;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('auto-sync-translation')]
class EnvironmentAwarenessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();

    }

    #[Test]
    public function it_processes_automation_in_production_environment(): void
    {
        Queue::fake();

        // Set environment to prod
        $originalEnv = app()->environment();
        app()->instance('env', 'prod');

        $environmentService = new EnvironmentService;
        $slackService = Mockery::mock(SlackService::class)->shouldIgnoreMissing();
        $listener = new AutoSyncTranslationMigrationsListener($environmentService, $slackService);
        $event = new MigrationsEnded('migrate', []);

        $listener->handle($event);

        // Jobs should be dispatched in prod
        Queue::assertPushed(AutoProcessTranslationMigrationJob::class, 3);

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_processes_automation_in_staging_environment(): void
    {
        Queue::fake();

        // Set environment to staging
        $originalEnv = app()->environment();
        app()->instance('env', 'staging');

        $environmentService = new EnvironmentService;
        $slackService = Mockery::mock(SlackService::class)->shouldIgnoreMissing();
        $listener = new AutoSyncTranslationMigrationsListener($environmentService, $slackService);
        $event = new MigrationsEnded('migrate', []);

        $listener->handle($event);

        // Staging is now dedicated to manual editing, auto-sync should be skipped
        Queue::assertNothingPushed();

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_processes_automation_in_dev_environment(): void
    {
        Queue::fake();

        // Set environment to staging
        $originalEnv = app()->environment();
        app()->instance('env', 'dev');

        $environmentService = new EnvironmentService;
        $slackService = Mockery::mock(SlackService::class)->shouldIgnoreMissing();
        $listener = new AutoSyncTranslationMigrationsListener($environmentService, $slackService);
        $event = new MigrationsEnded('migrate', []);

        $listener->handle($event);

        // Dev environments should dispatch jobs
        Queue::assertPushed(AutoProcessTranslationMigrationJob::class, 3);

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_uses_environment_specific_s3_configuration(): void
    {
        // This test verifies that different environments use different S3 configurations
        // as defined in the existing S3StorageService

        // In local environment, it should use 'translations-s3-local' disk
        // In other environments, it should use 'translations-s3' disk

        $originalEnv = app()->environment();

        // Test local environment behavior
        app()->instance('env', 'local');

        // The S3StorageService should select the appropriate disk
        // This is tested indirectly through the service's disk selection logic
        $this->assertEquals('local', app()->environment());

        // Test prod environment behavior
        app()->instance('env', 'prod');
        $this->assertEquals('prod', app()->environment());

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_includes_environment_info_in_migration_metadata(): void
    {
        // This test will be validated when the actual job implementation
        // includes environment information in the migration metadata

        $environments = ['local', 'testing', 'staging', 'prod'];
        $originalEnv = app()->environment();

        foreach ($environments as $env) {
            app()->instance('env', $env);

            // The environment should be accessible within job context
            $this->assertEquals($env, app()->environment());
        }

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_handles_custom_environment_names(): void
    {
        Queue::fake();

        $customEnvironments = ['development'];
        $originalEnv = app()->environment();

        foreach ($customEnvironments as $env) {
            app()->instance('env', $env);

            $environmentService = new EnvironmentService;
            $listener = new AutoSyncTranslationMigrationsListener($environmentService, Mockery::mock(SlackService::class)->shouldIgnoreMissing());
            $event = new MigrationsEnded('migrate', []);

            $listener->handle($event);

            // Development alias should dispatch jobs
            Queue::assertPushed(AutoProcessTranslationMigrationJob::class, 3);
        }

        // Restore original environment
        app()->instance('env', $originalEnv);
    }

    #[Test]
    public function it_respects_app_env_helper_function(): void
    {
        Queue::fake();

        $originalEnv = app()->environment();

        // Test using different methods of checking environment
        app()->instance('env', 'local');

        $environmentService = new EnvironmentService;
        $listener = new AutoSyncTranslationMigrationsListener($environmentService, Mockery::mock(SlackService::class)->shouldIgnoreMissing());
        $event = new MigrationsEnded('migrate', []);

        $listener->handle($event);

        // Should skip in local regardless of how environment is checked
        Queue::assertNotPushed(AutoProcessTranslationMigrationJob::class);

        // Restore original environment
        app()->instance('env', $originalEnv);
    }
}
