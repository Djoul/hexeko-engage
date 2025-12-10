<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression test for Sentry issue ENGAGE-MAIN-API-9Z
 * Tests that the import action handles duplicate key violations correctly
 * by using UPSERT logic instead of plain INSERT.
 *
 * @see https://upengage.sentry.io/issues/69029135
 */
#[Group('translation')]
#[Group('regression')]
#[Group('bug-fix')]
class ImportExportTranslationsActionTest extends TestCase
{
    use DatabaseTransactions;

    private ImportTranslationsAction $action;

    private MockInterface $exportTranslationsAction;

    private MockInterface $s3StorageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportTranslationsAction = Mockery::mock(ExportTranslationsAction::class);
        $this->s3StorageService = Mockery::mock(S3StorageService::class);

        // Use real services for translation management
        $this->action = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),
            $this->exportTranslationsAction,
            $this->s3StorageService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that reapplying the same migration file doesn't cause duplicate key errors
     * This simulates the exact scenario from the Sentry error:
     * - Migration file is applied once
     * - Due to reconciliation or retry, the same file is applied again
     * - Without UPSERT, this causes: SQLSTATE[23505]: Unique violation
     */
    #[Test]
    public function it_handles_reapplication_of_same_migration_without_duplicate_key_error(): void
    {
        // Arrange
        $interface = 'web_financer';
        $this->setupBackupMock($interface);
        $this->cleanupTranslationsForInterface($interface);

        // First migration data
        $migrationData = [
            'interface' => $interface,
            'translations' => [
                'communication.delete' => [
                    'fr-FR' => 'Supprimer la communication',
                    'en-UK' => 'Delete communication',
                ],
                'communication.create' => [
                    'fr-FR' => 'Créer une communication',
                    'en-UK' => 'Create communication',
                ],
            ],
        ];

        // Act - First application (should succeed)
        $firstResult = $this->action->execute($migrationData, $interface);

        // Assert first application succeeded
        $this->assertTrue($firstResult['success']);
        $this->assertEquals(2, $firstResult['summary']['new_keys_created']);

        // Get the IDs of created records
        $deleteKey = TranslationKey::where('key', 'delete')
            ->where('group', 'communication')
            ->where('interface_origin', $interface)
            ->first();

        $deleteValueFr = TranslationValue::where('translation_key_id', $deleteKey->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertNotNull($deleteKey);
        $this->assertNotNull($deleteValueFr);

        // Store original IDs to verify they're preserved
        $originalKeyId = $deleteKey->id;
        $originalValueId = $deleteValueFr->id;

        // Reset backup mock for second call
        $this->setupBackupMock($interface);

        // Act - Second application (simulates reconciliation retry)
        // This should NOT throw "duplicate key value violates unique constraint"
        $secondResult = $this->action->execute($migrationData, $interface);

        // Assert second application succeeded without errors
        $this->assertTrue($secondResult['success']);
        // No new keys should be created (they already exist)
        $this->assertEquals(0, $secondResult['summary']['new_keys_created']);
        // Values should be detected as unchanged
        $this->assertGreaterThanOrEqual(2, $secondResult['summary']['unchanged']);

        // Verify original records still exist with same IDs (not duplicated)
        $deleteKeyAfter = TranslationKey::where('key', 'delete')
            ->where('group', 'communication')
            ->where('interface_origin', $interface)
            ->first();

        $deleteValueFrAfter = TranslationValue::where('translation_key_id', $deleteKeyAfter->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertEquals($originalKeyId, $deleteKeyAfter->id);
        $this->assertEquals($originalValueId, $deleteValueFrAfter->id);

        // Verify no duplicate records were created
        $keyCount = TranslationKey::where('key', 'delete')
            ->where('group', 'communication')
            ->where('interface_origin', $interface)
            ->count();

        $valueCount = TranslationValue::where('translation_key_id', $deleteKeyAfter->id)
            ->where('locale', 'fr-FR')
            ->count();

        $this->assertEquals(1, $keyCount, 'Should have exactly 1 key record, not duplicates');
        $this->assertEquals(1, $valueCount, 'Should have exactly 1 value record, not duplicates');
    }

    /**
     * Test that parallel migrations don't cause race condition duplicate key errors
     * This simulates two migrations trying to create the same key concurrently
     */
    #[Test]
    public function it_handles_concurrent_migration_attempts_without_race_condition(): void
    {
        // Arrange
        $interface = 'web_financer';
        $this->cleanupTranslationsForInterface($interface);

        $migrationData = [
            'interface' => $interface,
            'translations' => [
                'test.concurrent' => [
                    'fr-FR' => 'Valeur test',
                ],
            ],
        ];

        // Create the key manually to simulate race condition
        // (another migration already created it)
        $existingKey = TranslationKey::create([
            'key' => 'concurrent',
            'group' => 'test',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur test',
        ]);

        $this->setupBackupMock($interface);

        // Act - Try to import the same key (simulates race condition)
        // This should NOT throw duplicate key error
        $result = $this->action->execute($migrationData, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['unchanged']);

        // Verify only one record exists
        $keyCount = TranslationKey::where('key', 'concurrent')
            ->where('group', 'test')
            ->where('interface_origin', $interface)
            ->count();

        $this->assertEquals(1, $keyCount, 'Should have exactly 1 key record despite race condition');
    }

    /**
     * Test that sequence reset works correctly to prevent ID conflicts
     * This test ensures PostgreSQL sequences are properly synchronized
     */
    #[Test]
    public function it_synchronizes_postgresql_sequences_correctly(): void
    {
        // Only run this test on PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test only runs on PostgreSQL');
        }

        // Arrange
        $interface = 'web_financer';
        $this->cleanupTranslationsForInterface($interface);
        $this->setupBackupMock($interface);

        // Create a translation key manually with a specific ID
        // This simulates bulk inserts that might desync the sequence
        DB::statement('ALTER SEQUENCE translation_keys_id_seq RESTART WITH 1000');
        DB::statement('ALTER SEQUENCE translation_values_id_seq RESTART WITH 2000');

        $manualKey = new TranslationKey([
            'key' => 'manual',
            'group' => 'test',
            'interface_origin' => $interface,
        ]);
        $manualKey->id = 1050;
        $manualKey->saveQuietly();

        $manualValue = new TranslationValue([
            'translation_key_id' => $manualKey->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur manuelle',
        ]);
        $manualValue->id = 2050;
        $manualValue->saveQuietly();

        // Act - Import new translations
        // The action should reset sequences before inserting
        $migrationData = [
            'interface' => $interface,
            'translations' => [
                'test.auto' => [
                    'fr-FR' => 'Valeur automatique',
                ],
            ],
        ];

        $result = $this->action->execute($migrationData, $interface);

        // Assert
        $this->assertTrue($result['success']);

        // Verify the new key was created with an ID > 1050
        $autoKey = TranslationKey::where('key', 'auto')
            ->where('group', 'test')
            ->where('interface_origin', $interface)
            ->first();

        $this->assertNotNull($autoKey);
        $this->assertGreaterThan(1050, $autoKey->id, 'Auto-generated ID should be greater than manual ID');

        // Verify the new value was created with an ID > 2050
        $autoValue = TranslationValue::where('translation_key_id', $autoKey->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertNotNull($autoValue);
        $this->assertGreaterThan(2050, $autoValue->id, 'Auto-generated value ID should be greater than manual ID');
    }

    /**
     * Test that update_existing_values flag works correctly with duplicate prevention
     */
    #[Test]
    public function it_handles_updates_without_creating_duplicates(): void
    {
        // Arrange
        $interface = 'web_financer';
        $this->cleanupTranslationsForInterface($interface);
        $this->setupBackupMock($interface);

        // Create existing translation
        $existingKey = TranslationKey::create([
            'key' => 'update',
            'group' => 'test',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Ancienne valeur',
        ]);

        // Act - Import with update flag
        $migrationData = [
            'interface' => $interface,
            'translations' => [
                'test.update' => [
                    'fr-FR' => 'Nouvelle valeur',
                ],
            ],
            'update_existing_values' => true,
        ];

        $this->setupBackupMock($interface);
        $result = $this->action->execute($migrationData, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(1, $result['summary']['values_updated']);

        // Verify only one record exists and was updated
        $updatedValue = TranslationValue::where('translation_key_id', $existingKey->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertEquals('Nouvelle valeur', $updatedValue->value);

        $valueCount = TranslationValue::where('translation_key_id', $existingKey->id)
            ->where('locale', 'fr-FR')
            ->count();

        $this->assertEquals(1, $valueCount, 'Should have exactly 1 value, not duplicates after update');
    }

    /**
     * Setup backup mock for tests
     */
    private function setupBackupMock(string $interface): void
    {
        $this->exportTranslationsAction
            ->shouldReceive('execute')
            ->with($interface)
            ->andReturn([
                'interface' => $interface,
                'exported_at' => Carbon::now()->toIso8601String(),
                'total_keys' => 0,
                'locales' => [],
                'translations' => [],
            ]);

        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->withAnyArgs()
            ->andReturn('backups/'.$interface.'/before-import_'.date('Y-m-d_His').'.json');
    }

    /**
     * Clean up translation keys and values for interface
     */
    private function cleanupTranslationsForInterface(string $interface): void
    {
        $existingKeys = TranslationKey::where('interface_origin', $interface)->get();

        foreach ($existingKeys as $key) {
            TranslationValue::where('translation_key_id', $key->id)->delete();
            $key->delete();
        }
    }
}
