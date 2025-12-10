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
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['translation_keys'], scope: 'test')]

#[Group('admin-panel')]
#[Group('translation')]
class ImportTranslationsActionWithBackupTest extends ProtectedRouteTestCase
{
    private ImportTranslationsAction $action;

    private MockInterface|S3StorageService $s3StorageService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure translations-s3-local disk for test environment
        Storage::fake('translations-s3-local');

        // Mock S3StorageService to use our faked storage
        $this->s3StorageService = Mockery::mock(S3StorageService::class);
        $this->app->instance(S3StorageService::class, $this->s3StorageService);

        // Setup the action with real services and mocked S3 service
        $this->action = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),
            app(ExportTranslationsAction::class),
            $this->s3StorageService
        );

        // Set a dummy financer_id in Context for any potential global scope queries
        Context::add('financer_id', 'test-financer-id');
    }

    #[Test]
    public function it_creates_json_backup_before_import(): void
    {
        // Freeze time for predictable filename
        Carbon::setTestNow(Carbon::parse('2024-01-15 14:30:00'));

        // Mock S3StorageService createUnifiedBackup method
        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->once()
            ->with(
                'web',
                'before-import',
                Mockery::type('string'),
                'json'
            )
            ->andReturnUsing(function ($interface, $operation, $content, $format): string {
                // Store the backup in our faked storage for verification
                $timestamp = '2024-01-15_143000';
                $filename = "{$interface}_{$operation}_{$timestamp}.{$format}";
                $path = "backups/{$interface}/{$filename}";
                Storage::disk('translations-s3-local')->put($path, $content);

                return $path;
            });

        // Arrange - Create existing translations
        $interface = 'web';

        $existingKey1 = TranslationKey::create([
            'key' => 'key1',
            'group' => 'existing',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey1->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur existante',
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey1->id,
            'locale' => 'en-UK',
            'value' => 'Existing value',
        ]);

        $existingKey2 = TranslationKey::create([
            'key' => 'key2',
            'group' => 'existing',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey2->id,
            'locale' => 'fr-FR',
            'value' => 'Autre valeur',
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey2->id,
            'locale' => 'en-UK',
            'value' => 'Other value',
        ]);

        // Data to import
        $data = [
            'interface' => 'web',
            'translations' => [
                'new.key' => [
                    'fr-FR' => 'Nouvelle valeur',
                    'en-UK' => 'New value',
                ],
            ],
        ];

        // Act
        $result = $this->action->execute($data, $interface);

        // Assert - Check backup file was created with unified naming
        $expectedFilename = 'web_before-import_2024-01-15_143000.json';
        Storage::disk('translations-s3-local')->assertExists("backups/web/{$expectedFilename}");

        // Verify JSON content
        $jsonContent = Storage::disk('translations-s3-local')->get("backups/web/{$expectedFilename}");
        $backupData = json_decode($jsonContent, true);

        // Check existing translations are in backup
        $this->assertArrayHasKey('interface', $backupData);
        $this->assertEquals('web', $backupData['interface']);
        $this->assertArrayHasKey('translations', $backupData);
        $this->assertArrayHasKey('existing.key1', $backupData['translations']);
        $this->assertArrayHasKey('existing.key2', $backupData['translations']);
        $this->assertEquals('Valeur existante', $backupData['translations']['existing.key1']['fr-FR']);
        $this->assertEquals('Existing value', $backupData['translations']['existing.key1']['en-UK']);
        $this->assertEquals('Autre valeur', $backupData['translations']['existing.key2']['fr-FR']);
        $this->assertEquals('Other value', $backupData['translations']['existing.key2']['en-UK']);

        // Check import was successful
        $this->assertTrue($result['success']);
        // The import creates one new key with two locale values
        $this->assertGreaterThanOrEqual(1, $result['summary']['new_keys_created'] + $result['summary']['new_values_created']);

        // Clean up
        Carbon::setTestNow();
    }

    #[Test]
    public function it_creates_backup_directory_if_not_exists(): void
    {
        // Freeze time
        Carbon::setTestNow(Carbon::parse('2024-02-20 10:15:00'));

        // Mock S3StorageService createUnifiedBackup method
        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->once()
            ->with(
                'mobile',
                'before-import',
                Mockery::type('string'),
                'json'
            )
            ->andReturnUsing(function ($interface, $operation, $content, $format): string {
                // Store the backup in our faked storage for verification
                $timestamp = '2024-02-20_101500';
                $filename = "{$interface}_{$operation}_{$timestamp}.{$format}";
                $path = "backups/{$interface}/{$filename}";
                Storage::disk('translations-s3-local')->put($path, $content);

                return $path;
            });

        // Arrange
        $interface = 'mobile';
        $data = [
            'interface' => 'mobile',
            'translations' => [
                'test.backup' => [
                    'fr-FR' => 'Test backup',
                ],
            ],
        ];

        // Ensure directory doesn't exist
        Storage::disk('translations-s3-local')->assertMissing('backups/mobile');

        // Act
        $result = $this->action->execute($data, $interface);

        // Assert
        Storage::disk('translations-s3-local')->assertExists('backups/mobile');

        $expectedFilename = 'mobile_before-import_2024-02-20_101500.json';
        Storage::disk('translations-s3-local')->assertExists("backups/mobile/{$expectedFilename}");

        $this->assertTrue($result['success']);

        // Clean up
        Carbon::setTestNow();
    }

    #[Test]
    public function it_generates_correct_json_format(): void
    {
        // Freeze time
        Carbon::setTestNow(Carbon::parse('2024-03-10 16:45:00'));

        // Mock S3StorageService createUnifiedBackup method
        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->once()
            ->with(
                'web',
                'before-import',
                Mockery::type('string'),
                'json'
            )
            ->andReturnUsing(function ($interface, $operation, $content, $format): string {
                // Store the backup in our faked storage for verification
                $timestamp = '2024-03-10_164500';
                $filename = "{$interface}_{$operation}_{$timestamp}.{$format}";
                $path = "backups/{$interface}/{$filename}";
                Storage::disk('translations-s3-local')->put($path, $content);

                return $path;
            });

        // Arrange
        $interface = 'web';

        // Create translations with various scenarios
        $key1 = TranslationKey::create([
            'key' => 'simple_key',
            'group' => null,
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $key1->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur simple',
        ]);

        $key2 = TranslationKey::create([
            'key' => 'complex_key',
            'group' => 'nested.group',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $key2->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur avec, virgule',
        ]);

        TranslationValue::create([
            'translation_key_id' => $key2->id,
            'locale' => 'en-UK',
            'value' => 'Value with "quotes"',
        ]);

        TranslationValue::create([
            'translation_key_id' => $key2->id,
            'locale' => 'es-ES',
            'value' => 'Valor con
nueva línea',
        ]);

        // Import data to trigger backup
        $data = [
            'interface' => 'web',
            'translations' => [
                'import.test' => [
                    'fr-FR' => 'Test import',
                ],
            ],
        ];

        // Act
        $this->action->execute($data, $interface);

        // Assert
        $expectedFilename = 'web_before-import_2024-03-10_164500.json';
        $jsonContent = Storage::disk('translations-s3-local')->get("backups/web/{$expectedFilename}");
        $backupData = json_decode($jsonContent, true);

        // Check JSON structure
        $this->assertArrayHasKey('interface', $backupData);
        $this->assertEquals('web', $backupData['interface']);
        $this->assertArrayHasKey('translations', $backupData);

        // Check that special characters are properly preserved in JSON
        $this->assertArrayHasKey('simple_key', $backupData['translations']);
        $this->assertArrayHasKey('nested.group.complex_key', $backupData['translations']);
        $this->assertEquals('Valeur avec, virgule', $backupData['translations']['nested.group.complex_key']['fr-FR']);
        $this->assertEquals('Value with "quotes"', $backupData['translations']['nested.group.complex_key']['en-UK']);
        $this->assertStringContainsString('nueva línea', $backupData['translations']['nested.group.complex_key']['es-ES']); // Newlines preserved

        // Clean up
        Carbon::setTestNow();
    }

    #[Test]
    public function it_does_not_create_backup_in_preview_mode(): void
    {
        // S3StorageService should not be called in preview mode
        $this->s3StorageService
            ->shouldNotReceive('createUnifiedBackup');

        // Arrange
        $interface = 'web';
        $data = [
            'interface' => 'web',
            'translations' => [
                'preview.test' => [
                    'fr-FR' => 'Preview test',
                ],
            ],
        ];

        // Act - Execute in preview mode
        $result = $this->action->execute($data, $interface, true);

        // Assert
        $this->assertTrue($result['preview']);
        Storage::disk('translations-s3-local')->assertMissing('backups/web');
    }

    #[Test]
    public function it_handles_empty_translations_in_backup(): void
    {
        // Freeze time
        Carbon::setTestNow(Carbon::parse('2024-04-01 12:00:00'));

        // Mock S3StorageService createUnifiedBackup method
        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->once()
            ->with(
                'mobile',
                'before-import',
                Mockery::type('string'),
                'json'
            )
            ->andReturnUsing(function ($interface, $operation, $content, $format): string {
                // Store the backup in our faked storage for verification
                $timestamp = '2024-04-01_120000';
                $filename = "{$interface}_{$operation}_{$timestamp}.{$format}";
                $path = "backups/{$interface}/{$filename}";
                Storage::disk('translations-s3-local')->put($path, $content);

                return $path;
            });

        // Arrange - No existing translations
        $interface = 'mobile';
        $data = [
            'interface' => 'mobile',
            'translations' => [
                'first.import' => [
                    'fr-FR' => 'Premier import',
                ],
            ],
        ];

        // Act
        $result = $this->action->execute($data, $interface);

        // Assert
        $expectedFilename = 'mobile_before-import_2024-04-01_120000.json';
        Storage::disk('translations-s3-local')->assertExists("backups/mobile/{$expectedFilename}");

        $jsonContent = Storage::disk('translations-s3-local')->get("backups/mobile/{$expectedFilename}");
        $backupData = json_decode($jsonContent, true);

        // Should have empty translations when no existing translations
        $this->assertArrayHasKey('interface', $backupData);
        $this->assertEquals('mobile', $backupData['interface']);
        $this->assertArrayHasKey('translations', $backupData);
        $this->assertEmpty($backupData['translations']); // Empty array for no translations

        $this->assertTrue($result['success']);

        // Clean up
        Carbon::setTestNow();
    }
}
