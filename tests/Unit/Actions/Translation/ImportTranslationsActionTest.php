<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use App\Services\TranslationMigrations\S3StorageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
#[Group('admin-panel')]
class ImportTranslationsActionTest extends TestCase
{
    use DatabaseTransactions;

    private ImportTranslationsAction $action;

    private MockInterface $translationKeyService;

    private MockInterface $translationValueService;

    private MockInterface $exportTranslationsAction;

    private MockInterface $s3StorageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationKeyService = Mockery::mock(TranslationKeyService::class);
        $this->translationValueService = Mockery::mock(TranslationValueService::class);
        $this->exportTranslationsAction = Mockery::mock(ExportTranslationsAction::class);
        $this->s3StorageService = Mockery::mock(S3StorageService::class);
        Storage::fake('public');

        $this->action = new ImportTranslationsAction(
            $this->translationKeyService,
            $this->translationValueService,
            $this->exportTranslationsAction,
            $this->s3StorageService
        );
    }

    #[Test]
    public function it_imports_multilingual_format_with_new_keys(): void
    {
        // This test uses real services to test the complete integration
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),
            $this->exportTranslationsAction,
            app(S3StorageService::class)
        );

        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'group.key1' => [
                    'fr-FR' => 'Valeur française',
                    'en-UK' => 'English value',
                ],
                'simple_key' => [
                    'fr-FR' => 'Simple valeur',
                ],
            ],
        ];

        $this->setupBackupMock($interface);

        // Clean up ALL existing keys for this interface to ensure clean state
        $this->cleanupTranslationsForInterface($interface);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        // The summary counts unique keys, not locale entries
        // group.key1 = 1 key
        // simple_key = 1 key
        // Total = 2 keys
        $this->assertEquals(2, $result['summary']['new_keys_created']);
        $this->assertEquals(0, $result['summary']['values_updated']);
        $this->assertEquals(0, $result['summary']['new_values_created']);

        // Verify database state
        $this->assertDatabaseHas('translation_keys', [
            'key' => 'key1',
            'group' => 'group',
            'interface_origin' => $interface,
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'simple_key',
            'group' => null,
            'interface_origin' => $interface,
        ]);

        $this->assertDatabaseHas('translation_values', [
            'locale' => 'fr-FR',
            'value' => 'Valeur française',
        ]);

        $this->assertDatabaseHas('translation_values', [
            'locale' => 'en-UK',
            'value' => 'English value',
        ]);

        $this->assertDatabaseHas('translation_values', [
            'locale' => 'fr-FR',
            'value' => 'Simple valeur',
        ]);
    }

    #[Test]
    public function it_updates_existing_translations_when_flag_is_enabled(): void
    {
        // This test uses real services to test value updates
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),

            $this->exportTranslationsAction,
            app(S3StorageService::class)
        );

        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'group.key1' => [
                    'fr-FR' => 'Nouvelle valeur',
                ],
            ],
            'update_existing_values' => true,
        ];

        $this->setupBackupMock($interface);

        // Clean up and create existing key with existing value
        $this->cleanupTranslationsForInterface($interface);

        $existingKey = TranslationKey::create([
            'key' => 'key1',
            'group' => 'group',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Ancienne valeur',
        ]);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(1, $result['summary']['values_updated']);
        $this->assertEquals(0, $result['summary']['new_values_created']);

        // Verify the value was actually updated
        $updatedValue = TranslationValue::where('translation_key_id', $existingKey->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertEquals('Nouvelle valeur', $updatedValue->value);
    }

    #[Test]
    public function it_does_not_update_existing_translations_when_flag_is_disabled(): void
    {
        // This test uses real services to test that values are NOT updated
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),

            $this->exportTranslationsAction,
            app(S3StorageService::class)
        );

        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'group.key1' => [
                    'fr-FR' => 'Nouvelle valeur',
                ],
            ],
            'update_existing_values' => false,
        ];

        $this->setupBackupMock($interface);

        // Clean up and create existing key with existing value
        $this->cleanupTranslationsForInterface($interface);

        $existingKey = TranslationKey::create([
            'key' => 'key1',
            'group' => 'group',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Ancienne valeur',
        ]);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(0, $result['summary']['values_updated']); // No updates
        $this->assertEquals(0, $result['summary']['new_values_created']);
        $this->assertEquals(1, $result['summary']['unchanged']); // Detected but not updated

        // Verify the value was NOT updated
        $unchangedValue = TranslationValue::where('translation_key_id', $existingKey->id)
            ->where('locale', 'fr-FR')
            ->first();

        $this->assertEquals('Ancienne valeur', $unchangedValue->value); // Still old value
    }

    #[Test]
    public function it_handles_preview_mode(): void
    {
        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'new.key' => [
                    'fr-FR' => 'Nouvelle clé',
                ],
            ],
        ];

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn(\Illuminate\Database\Eloquent\Collection::make([]));

        // Act
        $result = $this->action->execute($data, $interface, true);

        // Assert
        $this->assertTrue($result['preview']);
        $this->assertEquals(1, $result['summary']['new_keys']);
        $this->assertArrayHasKey('changes', $result);
        $this->assertCount(1, $result['changes']['new_keys']);
    }

    #[Test]
    public function it_validates_interface_match(): void
    {
        // Arrange
        $data = [
            'interface' => 'mobile',
            'translations' => [],
        ];

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->action->execute($data, 'web_financer');
    }

    #[Test]
    public function it_validates_json_structure(): void
    {
        // Arrange
        $data = [
            'translations' => 'invalid', // Should be array
        ];

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->action->execute($data, 'web_financer');
    }

    #[Test]
    public function it_handles_single_language_import(): void
    {
        // This test uses real services to test single language imports
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),

            $this->exportTranslationsAction,
            app(S3StorageService::class)
        );

        // Arrange
        $interface = 'web_financer';
        $singleLangData = [
            'internalCommunications' => [
                'title' => 'Communications',
                'published' => 'Published',
            ],
            'auth' => [
                'login' => 'Login',
            ],
        ];

        // Convert to expected format using DTO
        $dto = ImportTranslationDTO::fromFileUpload(
            json_encode($singleLangData),
            'en.json',
            $interface,
            'single'
        );

        $data = [
            'interface' => $interface,
            'translations' => $dto->translations,
        ];

        $this->setupBackupMock($interface);

        // Clean up existing keys for this interface
        $this->cleanupTranslationsForInterface($interface);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['summary']['new_keys_created']);

        // Verify the keys were created with correct locale
        $this->assertDatabaseHas('translation_keys', [
            'key' => 'title',
            'group' => 'internalCommunications',
            'interface_origin' => $interface,
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'published',
            'group' => 'internalCommunications',
            'interface_origin' => $interface,
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'login',
            'group' => 'auth',
            'interface_origin' => $interface,
        ]);

        // Verify values were created with correct locale (en-GB from @en.json filename)
        $titleKey = TranslationKey::where('key', 'title')
            ->where('group', 'internalCommunications')
            ->first();

        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $titleKey->id,
            'locale' => 'en-GB',
            'value' => 'Communications',
        ]);
    }

    #[Test]
    public function it_detects_unchanged_values(): void
    {
        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'group.key1' => [
                    'fr-FR' => 'Même valeur',
                ],
            ],
        ];

        $this->setupBackupMock($interface);

        $existingKey = Mockery::mock(TranslationKey::class)->makePartial();
        $existingKey->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $existingKey->shouldReceive('getAttribute')->with('group')->andReturn('group');
        $existingKey->shouldReceive('getAttribute')->with('key')->andReturn('key1');
        $existingKey->shouldReceive('setAttribute')->andReturnSelf();
        $existingKey->id = 1;
        $existingKey->group = 'group';
        $existingKey->key = 'key1';

        $existingValue = Mockery::mock(TranslationValue::class)->makePartial();
        $existingValue->shouldReceive('getAttribute')->with('locale')->andReturn('fr-FR');
        $existingValue->shouldReceive('getAttribute')->with('value')->andReturn('Même valeur');
        $existingValue->locale = 'fr-FR';
        $existingValue->value = 'Même valeur';

        $valuesCollection = Collection::make([$existingValue]);
        $existingKey->shouldReceive('getAttribute')->with('values')->andReturn($valuesCollection);
        $existingKey->values = $valuesCollection;

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn(\Illuminate\Database\Eloquent\Collection::make([$existingKey]));

        // Act
        $result = $this->action->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(0, $result['summary']['values_updated']);
        $this->assertEquals(1, $result['summary']['unchanged']);
    }

    #[Test]
    public function it_adds_new_locale_to_existing_key(): void
    {
        // This test uses real services to test adding new locales to existing keys
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),

            $this->exportTranslationsAction,
            app(S3StorageService::class)
        );

        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'group.key1' => [
                    'es-ES' => 'Valor español', // New locale
                ],
            ],
        ];

        $this->setupBackupMock($interface);

        // Clean up and create existing key with existing value
        $this->cleanupTranslationsForInterface($interface);

        $existingKey = TranslationKey::create([
            'key' => 'key1',
            'group' => 'group',
            'interface_origin' => $interface,
        ]);

        TranslationValue::create([
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur française',
        ]);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['summary']['new_keys_created']);
        $this->assertEquals(0, $result['summary']['values_updated']);
        $this->assertEquals(1, $result['summary']['new_values_created']);

        // Verify the new locale was added
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $existingKey->id,
            'locale' => 'es-ES',
            'value' => 'Valor español',
        ]);

        // Verify the original locale is still there
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr-FR',
            'value' => 'Valeur française',
        ]);
    }

    #[Test]
    public function it_creates_json_backup_before_import(): void
    {
        // Freeze time for predictable filename
        Carbon::setTestNow(Carbon::parse('2024-01-15 14:30:00'));

        // Mock S3StorageService to verify backup creation
        $mockS3Service = Mockery::mock(S3StorageService::class);
        $mockS3Service->shouldReceive('createUnifiedBackup')
            ->once()
            ->with(
                'web_financer',
                'before-import',
                Mockery::on(function ($content): bool {
                    // Verify the content is valid JSON with expected structure
                    $data = json_decode($content, true);

                    return isset($data['interface']) &&
                           $data['interface'] === 'web_financer' &&
                           isset($data['exported_at']) &&
                           isset($data['translations']) &&
                           isset($data['translations']['existing.key1']) &&
                           isset($data['translations']['existing.key2']);
                }),
                'json'
            )
            ->andReturn('backups/web_financer/web_financer_before-import_2024-01-15_143000.json');

        // This test uses real services except for S3StorageService
        $realAction = new ImportTranslationsAction(
            app(TranslationKeyService::class),
            app(TranslationValueService::class),

            $this->exportTranslationsAction,
            $mockS3Service
        );

        // Arrange
        $interface = 'web_financer';
        $data = [
            'interface' => 'web_financer',
            'translations' => [
                'test.key' => [
                    'fr-FR' => 'Test value',
                ],
            ],
        ];

        $exportData = [
            'interface' => 'web_financer',
            'exported_at' => '2024-01-15T14:30:00+00:00',
            'total_keys' => 2,
            'locales' => ['fr-FR', 'en-UK'],
            'translations' => [
                'existing.key1' => [
                    'fr-FR' => 'Valeur existante',
                    'en-UK' => 'Existing value',
                ],
                'existing.key2' => [
                    'fr-FR' => 'Autre valeur',
                    'en-UK' => 'Other value',
                ],
            ],
        ];

        $this->exportTranslationsAction
            ->shouldReceive('execute')
            ->with($interface)
            ->once()
            ->andReturn($exportData);

        // Clean up existing keys for this interface
        $this->cleanupTranslationsForInterface($interface);

        // Act
        $result = $realAction->execute($data, $interface);

        // Assert
        $this->assertTrue($result['success']);

        // Verify the new key was created
        $this->assertDatabaseHas('translation_keys', [
            'key' => 'key',
            'group' => 'test',
            'interface_origin' => $interface,
        ]);

        // Clean up
        Carbon::setTestNow();
    }

    /**
     * Setup backup mock for tests that don't focus on backup functionality
     */
    private function setupBackupMock(string $interface): void
    {
        $this->exportTranslationsAction
            ->shouldReceive('execute')
            ->with($interface)
            ->once()
            ->andReturn([
                'interface' => $interface,
                'exported_at' => Carbon::now()->toIso8601String(),
                'total_keys' => 0,
                'locales' => [],
                'translations' => [],
            ]);

        // Also mock the S3StorageService for tests that create backups
        $this->s3StorageService
            ->shouldReceive('createUnifiedBackup')
            ->withAnyArgs()
            ->andReturn('backups/'.$interface.'/before-import_'.date('Y-m-d_His').'.json');
    }

    /**
     * Clean up translation keys and values for interface to avoid foreign key violations
     */
    private function cleanupTranslationsForInterface(string $interface): void
    {
        $existingKeys = TranslationKey::where('interface_origin', $interface)->get();

        foreach ($existingKeys as $key) {
            // Delete associated values first
            TranslationValue::where('translation_key_id', $key->id)->delete();
            // Then delete the key
            $key->delete();
        }
    }
}
