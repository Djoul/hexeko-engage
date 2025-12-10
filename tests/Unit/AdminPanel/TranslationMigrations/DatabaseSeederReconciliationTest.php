<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\TranslationMigrations;

use App\Services\EnvironmentService;
use App\Services\SlackService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('translation')]
class DatabaseSeederReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private MockInterface|EnvironmentService $environmentService;

    private MockInterface|SlackService $slackService;

    protected function setUp(): void
    {

        parent::setUp();

        Cache::flush();
        Config::set('translations.reconciliation.auto_reconcile_after_seed', true);
        Config::set('translations.reconciliation.throttle_seconds', 300);

        $this->environmentService = Mockery::mock(EnvironmentService::class);
        $this->slackService = Mockery::mock(SlackService::class);

        app()->instance(EnvironmentService::class, $this->environmentService);
        app()->instance(SlackService::class, $this->slackService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();

    }

    #[Test]
    public function it_reconciles_after_seed_on_dev_with_console_feedback(): void
    {
        $this->environmentService
            ->shouldReceive('shouldReconcileAfterSeed')
            ->once()
            ->andReturn(true);

        Artisan::shouldReceive('call')
            ->once()
            ->with('translations:auto-reconcile', ['--all' => true, '--force' => true])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, 'Seed terminé - réconciliation effectuée')), '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $seeder = $this->makeSeederWithCommandMock();

        $this->invokeReconciliation($seeder);

        $this->assertTrue(Cache::has('last_reconciliation_seeder'));
    }

    #[Test]
    public function it_skips_reconciliation_when_throttled_and_notifies_slack(): void
    {
        Cache::put('last_reconciliation_seeder', Date::now(), 300);

        $this->environmentService
            ->shouldReceive('shouldReconcileAfterSeed')
            ->once()
            ->andReturn(true);

        Artisan::shouldReceive('call')->never();

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, 'Throttle actif')), '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $seeder = $this->makeSeederWithCommandMock();

        $this->invokeReconciliation($seeder);

        $this->assertTrue(Cache::has('last_reconciliation_seeder'));
    }

    #[Test]
    public function it_skips_reconciliation_on_non_dev_environments(): void
    {
        $this->environmentService
            ->shouldReceive('shouldReconcileAfterSeed')
            ->once()
            ->andReturn(false);

        Artisan::shouldReceive('call')->never();
        $this->slackService->shouldNotReceive('sendToPublicChannel');

        $seeder = $this->makeSeederWithCommandMock();

        $this->invokeReconciliation($seeder);

        $this->assertFalse(Cache::has('last_reconciliation_seeder'));
    }

    #[Test]
    public function it_sends_slack_message_when_no_console_is_available(): void
    {
        $this->environmentService
            ->shouldReceive('shouldReconcileAfterSeed')
            ->once()
            ->andReturn(true);

        Artisan::shouldReceive('call')
            ->once()
            ->with('translations:auto-reconcile', ['--all' => true, '--force' => true])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with('🔄 Starting translation reconciliation from seeder...', '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with('Seed terminé - réconciliation effectuée avec succès', '#up-engage-tech')
            ->andReturn(['ok' => true]);

        $seeder = $this->makeSeeder();

        $this->invokeReconciliation($seeder);

        $this->assertTrue(Cache::has('last_reconciliation_seeder'));
    }

    private function makeSeeder(): DatabaseSeeder
    {
        $team = ModelFactory::createTeam();

        return new DatabaseSeeder($team);
    }

    private function makeSeederWithCommandMock(): DatabaseSeeder
    {
        $seeder = $this->makeSeeder();
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('info')->andReturnNull();
        $command->shouldReceive('warn')->andReturnNull();
        $command->shouldReceive('error')->andReturnNull();
        $command->shouldReceive('line')->andReturnNull();

        $reflection = new ReflectionProperty($seeder, 'command');
        $reflection->setAccessible(true);
        $reflection->setValue($seeder, $command);

        return $seeder;
    }

    private function invokeReconciliation(DatabaseSeeder $seeder): void
    {
        $method = new ReflectionMethod($seeder, 'reconcileTranslationsIfNeeded');
        $method->setAccessible(true);
        $method->invoke($seeder);
    }
}
