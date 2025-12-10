<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Jobs\TranslationMigrations\AutoProcessTranslationMigrationJob;
use App\Listeners\AutoSyncTranslationMigrationsListener;
use App\Services\EnvironmentService;
use App\Services\SlackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('auto-sync-translation')]
class AutoSyncTranslationMigrationsListenerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        putenv('ALLOW_AUTO_SYNC_LISTENER_TESTING=true');
        $_ENV['ALLOW_AUTO_SYNC_LISTENER_TESTING'] = 'true';
        $_SERVER['ALLOW_AUTO_SYNC_LISTENER_TESTING'] = 'true';
    }

    private MockInterface|EnvironmentService $environmentService;

    private MockInterface|SlackService $slackService;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        Config::set('translations.reconciliation.throttle_seconds', 300);

        $this->environmentService = Mockery::mock(EnvironmentService::class);
        $this->slackService = Mockery::mock(SlackService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_implements_should_queue_interface(): void
    {
        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $this->assertInstanceOf(ShouldQueue::class, $listener);
    }

    #[Test]
    public function it_skips_when_auto_sync_is_disabled(): void
    {
        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(false);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('dev');

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_skips_on_local_environment(): void
    {
        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(true);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('local');

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_skips_on_staging_environment(): void
    {
        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(true);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('staging');

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_dispatches_jobs_on_dev_environment(): void
    {
        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(true);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('dev');

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, '✅ Auto-sync déclenchée')), '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertPushed(AutoProcessTranslationMigrationJob::class, 3);
        $this->assertTrue(Cache::has('last_reconciliation_listener'));
    }

    #[Test]
    public function it_dispatches_jobs_on_production_environment(): void
    {
        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(true);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('production');

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, '✅ Auto-sync déclenchée')), '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertPushed(AutoProcessTranslationMigrationJob::class, 3);
        $this->assertTrue(Cache::has('last_reconciliation_listener'));
    }

    #[Test]
    public function it_respects_throttle_and_notifies_slack(): void
    {
        Cache::put('last_reconciliation_listener', Date::now(), 300);

        $this->environmentService
            ->shouldReceive('shouldAutoSync')
            ->once()
            ->andReturn(true);

        $this->environmentService
            ->shouldReceive('getCurrentEnvironment')
            ->atLeast()->once()
            ->andReturn('dev');

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, 'throttle')), '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $listener = new AutoSyncTranslationMigrationsListener(
            $this->environmentService,
            $this->slackService
        );

        $listener->handle(new MigrationsEnded('migrate', []));

        Queue::assertNothingPushed();
    }
}
