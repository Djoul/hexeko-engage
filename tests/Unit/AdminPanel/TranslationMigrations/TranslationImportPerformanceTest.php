<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\TranslationMigrations;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use App\Services\TranslationMigrations\S3StorageService;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['translation_activity_logs', 'translation_keys',
    'translation_migrations',
    'translation_values'], scope: 'test')]

#[Group('translation')]
#[Group('translation-migrations')]
#[Group('performance')]
#[Group('n+1-regression')]
class TranslationImportPerformanceTest extends ProtectedRouteTestCase
{
    private ImportTranslationsAction $action;

    private S3StorageService $s3Service;

    protected function setUp(): void
    {
        parent::setUp();

        $exportAction = Mockery::mock(ExportTranslationsAction::class);
        $exportAction->shouldReceive('execute')
            ->andReturn([
                'interface' => 'test_interface',
                'exported_at' => now()->toIso8601String(),
                'total_keys' => 0,
                'locales' => [],
                'translations' => [],
            ]);

        $this->s3Service = Mockery::mock(S3StorageService::class);
        $this->s3Service->shouldReceive('createUnifiedBackup')
            ->andReturn('backups/test_backup.json');

        $this->action = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),
            $exportAction,
            $this->s3Service
        );

        // Clean state
        $this->cleanupTranslationsForInterface('test_interface');
    }

    /**
     * Test Fix for ENGAGE-MAIN-API-A2: Bulk INSERT prevents N+1 queries
     *
     * Before fix: 126 individual INSERT queries
     * After fix: 1-2 bulk INSERT queries
     */
    #[Test]
    public function it_prevents_n_plus_1_queries_when_inserting_new_translation_values(): void
    {
        // Arrange: Create import data with 50 keys and 3 locales each (150 values total)
        $translations = [];
        for ($i = 1; $i <= 50; $i++) {
            $translations["group.key{$i}"] = [
                'fr-FR' => "Valeur française {$i}",
                'en-UK' => "English value {$i}",
                'es-ES' => "Valor español {$i}",
            ];
        }

        $data = [
            'interface' => 'test_interface',
            'translations' => $translations,
        ];

        // Act: Enable query logging and execute import
        DB::enableQueryLog();
        $result = $this->action->execute($data, 'test_interface');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Verify success
        $this->assertTrue($result['success']);
        $this->assertEquals(50, $result['summary']['new_keys_created']);
        $this->assertEquals(0, $result['summary']['new_values_created']); // All values created with keys

        // Count INSERT queries for translation_values
        $insertQueries = array_filter($queries, function (array $query): bool {
            return str_contains((string) $query['query'], 'insert into "translation_values"')
                || str_contains((string) $query['query'], 'INSERT INTO translation_values');
        });

        // CRITICAL ASSERTION: Should be 1 bulk INSERT, not 150 individual INSERTs
        // Allow up to 2 queries for PostgreSQL-specific operations
        $this->assertLessThanOrEqual(
            2,
            count($insertQueries),
            sprintf(
                "Expected at most 2 bulk INSERT queries, but found %d queries. This indicates N+1 problem ENGAGE-MAIN-API-A2 is not fixed!\nQueries: %s",
                count($insertQueries),
                json_encode(array_column($insertQueries, 'query'), JSON_PRETTY_PRINT)
            )
        );

        // Verify all values were created
        $this->assertEquals(150, TranslationValue::count());
    }

    /**
     * Test Fix for ENGAGE-MAIN-API-97: Bulk UPDATE prevents N+1 queries
     *
     * Before fix: 44 individual SELECT + UPDATE queries
     * After fix: 1-2 bulk UPDATE queries
     */
    #[Test]
    public function it_prevents_n_plus_1_queries_when_updating_existing_translation_values(): void
    {
        // Arrange: Create existing keys with values
        for ($i = 1; $i <= 50; $i++) {
            $key = TranslationKey::create([
                'key' => "key{$i}",
                'group' => 'group',
                'interface_origin' => 'test_interface',
            ]);

            TranslationValue::create([
                'translation_key_id' => $key->id,
                'locale' => 'fr-FR',
                'value' => "Ancienne valeur {$i}",
            ]);
        }

        // Prepare update data
        $translations = [];
        for ($i = 1; $i <= 50; $i++) {
            $translations["group.key{$i}"] = [
                'fr-FR' => "Nouvelle valeur {$i}",
            ];
        }

        $data = [
            'interface' => 'test_interface',
            'translations' => $translations,
            'update_existing_values' => true, // Enable updates
        ];

        // Act: Enable query logging and execute import
        DB::enableQueryLog();
        $result = $this->action->execute($data, 'test_interface');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Verify success
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(50, $result['summary']['values_updated']);

        // Count UPDATE queries for translation_values
        $updateQueries = array_filter($queries, function (array $query): bool {
            return str_contains((string) $query['query'], 'update "translation_values"')
                || str_contains((string) $query['query'], 'UPDATE translation_values');
        });

        // CRITICAL ASSERTION: Should be 1 bulk UPDATE, not 50 individual UPDATEs
        // Allow up to 2 queries for PostgreSQL CASE statement
        $this->assertLessThanOrEqual(
            2,
            count($updateQueries),
            sprintf(
                "Expected at most 2 bulk UPDATE queries, but found %d queries. This indicates N+1 problem ENGAGE-MAIN-API-97 is not fixed!\nQueries: %s",
                count($updateQueries),
                json_encode(array_column($updateQueries, 'query'), JSON_PRETTY_PRINT)
            )
        );

        // Verify all values were updated
        $updatedValue = TranslationValue::first();
        $this->assertStringStartsWith('Nouvelle valeur', $updatedValue->value);
    }

    /**
     * Test combined scenario: Mix of new keys, new values, and updates
     */
    #[Test]
    public function it_prevents_n_plus_1_queries_in_mixed_import_scenario(): void
    {
        // Arrange: Create some existing keys
        for ($i = 1; $i <= 20; $i++) {
            $key = TranslationKey::create([
                'key' => "existing_key{$i}",
                'group' => 'existing',
                'interface_origin' => 'test_interface',
            ]);

            TranslationValue::create([
                'translation_key_id' => $key->id,
                'locale' => 'fr-FR',
                'value' => "Valeur existante {$i}",
            ]);
        }

        // Prepare mixed data: existing keys (updates), new keys, and new locales
        $translations = [];

        // Update existing keys
        for ($i = 1; $i <= 20; $i++) {
            $translations["existing.existing_key{$i}"] = [
                'fr-FR' => "Valeur mise à jour {$i}",
                'en-UK' => "New locale value {$i}", // New locale for existing key
            ];
        }

        // Add completely new keys
        for ($i = 1; $i <= 30; $i++) {
            $translations["new.new_key{$i}"] = [
                'fr-FR' => "Nouvelle clé {$i}",
                'en-UK' => "New key {$i}",
            ];
        }

        $data = [
            'interface' => 'test_interface',
            'translations' => $translations,
            'update_existing_values' => true,
        ];

        // Act: Enable query logging
        DB::enableQueryLog();
        $result = $this->action->execute($data, 'test_interface');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Verify success
        $this->assertTrue($result['success']);
        $this->assertEquals(30, $result['summary']['new_keys_created']);
        $this->assertEquals(20, $result['summary']['values_updated']);
        $this->assertEquals(20, $result['summary']['new_values_created']); // New locales

        // Count total INSERT/UPDATE queries
        $writeQueries = array_filter($queries, function (array $query): bool {
            $sql = (string) $query['query'];

            return str_contains($sql, 'insert into "translation_values"')
                || str_contains($sql, 'INSERT INTO translation_values')
                || str_contains($sql, 'update "translation_values"')
                || str_contains($sql, 'UPDATE translation_values');
        });

        // CRITICAL ASSERTION: Should have very few queries despite 70 operations
        // Maximum expected: 2 INSERTs + 2 UPDATEs = 4 queries
        $this->assertLessThanOrEqual(
            5,
            count($writeQueries),
            sprintf(
                "Expected at most 5 bulk queries for mixed operations, but found %d queries.\nThis indicates N+1 problems are not fully fixed!\nQueries: %s",
                count($writeQueries),
                json_encode(array_column($writeQueries, 'query'), JSON_PRETTY_PRINT)
            )
        );
    }

    /**
     * Test large dataset (realistic migration scenario)
     */
    #[Test]
    public function it_handles_large_migration_without_n_plus_1_queries(): void
    {
        // Arrange: Simulate a realistic migration with 100 keys × 5 locales = 500 values
        $translations = [];
        $locales = ['fr-FR', 'en-UK', 'es-ES', 'de-DE', 'it-IT'];

        for ($i = 1; $i <= 100; $i++) {
            $keyValues = [];
            foreach ($locales as $locale) {
                $keyValues[$locale] = "Translation {$i} in {$locale}";
            }
            $translations["module.key{$i}"] = $keyValues;
        }

        $data = [
            'interface' => 'test_interface',
            'translations' => $translations,
        ];

        // Act: Enable query logging
        DB::enableQueryLog();
        $startTime = microtime(true);

        $result = $this->action->execute($data, 'test_interface');

        $executionTime = microtime(true) - $startTime;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert: Verify success
        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['summary']['new_keys_created']);

        // Count write queries
        $writeQueries = array_filter($queries, function (array $query): bool {
            $sql = (string) $query['query'];

            return str_contains($sql, 'insert into "translation_values"')
                || str_contains($sql, 'INSERT INTO translation_values');
        });

        // CRITICAL: Should be very few queries even for 500 values
        $this->assertLessThanOrEqual(
            3,
            count($writeQueries),
            sprintf(
                'Large dataset (500 values) generated %d INSERT queries. Expected at most 3 bulk queries!',
                count($writeQueries)
            )
        );

        // Performance assertion: Should complete in reasonable time
        $this->assertLessThan(
            5.0,
            $executionTime,
            sprintf(
                'Large import took %.2f seconds. Expected < 5 seconds with bulk operations.',
                $executionTime
            )
        );

        // Verify data integrity
        $this->assertEquals(500, TranslationValue::count());
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
