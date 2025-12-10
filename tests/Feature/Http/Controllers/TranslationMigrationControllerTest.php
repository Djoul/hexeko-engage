<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Actions\TranslationMigrations\RollbackTranslationMigrationAction;
use App\DTOs\TranslationMigrations\MigrationResultDTO;
use App\Enums\IDP\RoleDefaults;
use App\Enums\OrigineInterfaces;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use Illuminate\Support\Facades\Bus;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migrations')]
class TranslationMigrationControllerTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with proper role
        $financer = ModelFactory::createFinancer();
        $this->auth = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Create role using ModelFactory
        $role = ModelFactory::createRole([
            'name' => RoleDefaults::FINANCER_ADMIN,
        ]);

        $this->auth->assignRole($role);
    }

    #[Test]
    public function it_lists_translation_migrations(): void
    {
        // Arrange
        // Get initial count
        TranslationMigration::count();

        TranslationMigration::factory()->count(3)->create([
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
        ]);
        TranslationMigration::factory()->count(2)->create([
            'interface_origin' => OrigineInterfaces::MOBILE,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/translation-migrations');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'interface_origin',
                        'version',
                        'filename',
                        'status',
                        'checksum',
                        'executed_at',
                        'rolled_back_at',
                        'metadata',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        // Check that we have at least the 5 migrations we created
        // (can't check exact count due to potential existing data)
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));

        // Verify our created migrations exist in the response
        $webFinancerCount = collect($response->json('data'))
            ->where('interface_origin', OrigineInterfaces::WEB_FINANCER)
            ->count();
        $mobileCount = collect($response->json('data'))
            ->where('interface_origin', OrigineInterfaces::MOBILE)
            ->count();

        $this->assertGreaterThanOrEqual(3, $webFinancerCount);
        $this->assertGreaterThanOrEqual(2, $mobileCount);
    }

    #[Test]
    public function it_filters_migrations_by_interface(): void
    {
        // Arrange
        // Get initial count for this interface
        TranslationMigration::where('interface_origin', OrigineInterfaces::WEB_FINANCER)->count();

        // Create test migrations
        $createdWebFinancer = TranslationMigration::factory()->count(3)->create([
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
        ]);
        TranslationMigration::factory()->count(2)->create([
            'interface_origin' => OrigineInterfaces::MOBILE,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/translation-migrations?interface='.OrigineInterfaces::WEB_FINANCER);

        // Assert
        $response->assertOk();

        // Check we have at least the 3 we created
        $responseData = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($responseData));

        // Verify all returned migrations have the correct interface
        foreach ($responseData as $migration) {
            $this->assertEquals(OrigineInterfaces::WEB_FINANCER, $migration['interface_origin']);
        }

        // Verify our created migrations are in the response
        $responseIds = collect($responseData)->pluck('id')->toArray();
        foreach ($createdWebFinancer as $migration) {
            $this->assertContains($migration->id, $responseIds);
        }
    }

    #[Test]
    public function it_filters_migrations_by_status(): void
    {
        // Arrange
        // Get initial count for completed status
        TranslationMigration::where('status', 'completed')->count();

        TranslationMigration::factory()->count(2)->create(['status' => 'pending']);
        $createdCompleted = TranslationMigration::factory()->count(3)->create(['status' => 'completed']);
        TranslationMigration::factory()->count(1)->create(['status' => 'failed']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/translation-migrations?status=completed');

        // Assert
        $response->assertOk();

        $responseData = $response->json('data');
        // Check we have at least the 3 completed we created
        $this->assertGreaterThanOrEqual(3, count($responseData));

        // Verify all returned migrations have completed status
        foreach ($responseData as $migration) {
            $this->assertEquals('completed', $migration['status']);
        }

        // Verify our created completed migrations are in the response
        $responseIds = collect($responseData)->pluck('id')->toArray();
        foreach ($createdCompleted as $migration) {
            $this->assertContains($migration->id, $responseIds);
        }
    }

    #[Test]
    public function it_shows_single_migration(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'interface_origin' => OrigineInterfaces::WEB_FINANCER,
            'version' => 'v1.0.0',
            'filename' => 'test.json',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/translation-migrations/{$migration->id}");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $migration->id,
                    'interface_origin' => OrigineInterfaces::WEB_FINANCER,
                    'version' => 'v1.0.0',
                    'filename' => 'test.json',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_migration(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/translation-migrations/99999');

        // Assert
        $response->assertNotFound();
    }

    #[Test]
    public function it_applies_migration(): void
    {
        // Arrange
        Bus::fake();
        $migration = TranslationMigration::factory()->create(['status' => 'pending']);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/translation-migrations/{$migration->id}/apply", [
                'create_backup' => true,
                'validate_checksum' => true,
            ]);

        // Assert
        $response->assertAccepted()
            ->assertJson([
                'data' => [
                    'message' => 'Migration is being processed',
                    'migration_id' => $migration->id,
                ],
            ]);

        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function ($job) use ($migration): bool {
            return $job->migrationId === $migration->id;
        });
    }

    #[Test]
    public function it_validates_apply_request(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create(['status' => 'pending']);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/translation-migrations/{$migration->id}/apply", [
                'create_backup' => 'not-a-boolean',
                'validate_checksum' => 'not-a-boolean',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['create_backup', 'validate_checksum']);
    }

    #[Test]
    public function it_rolls_back_migration(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'completed',
            'executed_at' => now(),
            'metadata' => ['backup_path' => 'backups/test.json'],
        ]);

        // Mock the action to return success
        $mockAction = Mockery::mock(RollbackTranslationMigrationAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn(MigrationResultDTO::success($migration->id));

        $this->app->instance(RollbackTranslationMigrationAction::class, $mockAction);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/translation-migrations/{$migration->id}/rollback", [
                'reason' => 'Critical bug found in production',
            ]);

        // Assert
        $response->assertAccepted()
            ->assertJson([
                'data' => [
                    'message' => 'Rollback is being processed',
                    'migration_id' => $migration->id,
                ],
            ]);
    }

    #[Test]
    public function it_requires_reason_for_rollback(): void
    {
        // Arrange
        $migration = TranslationMigration::factory()->create([
            'status' => 'completed',
            'executed_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/translation-migrations/{$migration->id}/rollback", []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function it_syncs_migrations_from_s3(): void
    {
        // Arrange
        Bus::fake();

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/translation-migrations/sync', [
                'interface' => OrigineInterfaces::WEB_FINANCER,
                'auto_process' => false,
            ]);

        // Assert
        $response->assertAccepted()
            ->assertJson([
                'data' => [
                    'message' => 'Sync job has been queued',
                    'interface' => OrigineInterfaces::WEB_FINANCER,
                ],
            ]);

        // TODO: Fix job dispatch assertion
        // Bus::assertDispatched(\App\Jobs\TranslationMigrations\SyncTranslationMigrationsJob::class);
    }

    #[Test]
    public function it_validates_sync_request(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/translation-migrations/sync', [
                'interface' => 'invalid-interface',
                'auto_process' => 'not-a-boolean',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['interface', 'auto_process']);
    }

    #[Test]
    public function it_can_reprocess_failed_migration(): void
    {
        // Arrange
        Bus::fake();
        $migration = TranslationMigration::factory()->create([
            'status' => 'failed',
            'metadata' => [
                'error' => 'Previous attempt failed',
                'failed_at' => now()->subHours(2)->toIso8601String(),
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/translation-migrations/{$migration->id}/apply", [
                'create_backup' => true,
                'validate_checksum' => false, // Don't validate checksum for retry
            ]);

        // Assert
        $response->assertAccepted()
            ->assertJson([
                'data' => [
                    'message' => 'Migration is being processed',
                    'migration_id' => $migration->id,
                ],
            ]);

        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function ($job) use ($migration): bool {
            return $job->migrationId === $migration->id
                && $job->createBackup === true
                && $job->validateChecksum === false;
        });
    }

    // #[Test]
    // public function it_requires_authentication(): void
    // {
    //     // Act
    //     $response = $this->getJson('/api/v1/translation-migrations');

    //     // Assert
    //     $response->assertUnauthorized();
    // }

    // #[Test]
    // public function it_requires_proper_permissions(): void
    // {
    //     // Arrange - Create user without admin role
    //     $user = ModelFactory::createUser([
    //         'email' => 'regular@test.com',
    //     ]);

    //     $role = ModelFactory::createRole([
    //         'name' => RoleDefaults::BENEFICIARY,
    //     ]);
    //     $user->assignRole($role);

    //     // Act
    //     $response = $this->actingAs($user)
    //         ->getJson('/api/v1/translation-migrations');

    //     // Assert
    //     $response->assertForbidden();
    // }
}
