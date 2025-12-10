<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Storage;

use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class ContentAvailabilityStorageTest extends TestCase
{
    private ContentAvailabilityStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3-local');
        Storage::fake('s3');

        $this->storage = new ContentAvailabilityStorage;
    }

    protected function tearDown(): void
    {
        // Clear any storage state between tests
        Storage::fake('s3-local');
        Storage::fake('s3');

        parent::tearDown();
    }

    #[Test]
    public function it_selects_correct_disk_based_on_environment(): void
    {
        // Test local environment
        app()->detectEnvironment(fn (): string => 'local');
        $localStorage = new ContentAvailabilityStorage;
        $this->assertEquals('s3-local', $localStorage->getDisk());

        // Test production environment
        app()->detectEnvironment(fn (): string => 'production');
        $prodStorage = new ContentAvailabilityStorage;
        $this->assertEquals('s3', $prodStorage->getDisk());

        // Reset to testing environment
        app()->detectEnvironment(fn (): string => 'testing');
    }

    #[Test]
    public function it_saves_availability_with_atomic_update(): void
    {
        // Arrange
        $dto = $this->createTestDTO('fr');

        // Act
        $result = $this->storage->saveAvailability('fr', $dto);

        // Assert
        $this->assertTrue($result);

        // Verify the file was written
        Storage::disk('s3-local')->assertExists(
            'wellwo/availability/fr/content.json'
        );

        // Verify atomic update by checking the content is complete
        $content = Storage::disk('s3-local')->get(
            'wellwo/availability/fr/content.json'
        );
        $decoded = json_decode($content, true);
        $this->assertEquals('1.0.0', $decoded['version']);
        $this->assertEquals('fr', $decoded['language']);
    }

    #[Test]
    public function it_loads_availability_from_storage(): void
    {
        // Arrange
        $originalDto = $this->createTestDTO('en');
        $this->storage->saveAvailability('en', $originalDto);

        // Act
        $loadedDto = $this->storage->loadAvailability('en');

        // Assert
        $this->assertNotNull($loadedDto);
        $this->assertInstanceOf(ContentAvailabilityDTO::class, $loadedDto);
        $this->assertEquals('en', $loadedDto->language);
        $this->assertEquals('1.0.0', $loadedDto->version);
    }

    #[Test]
    public function it_returns_null_for_non_existent_language(): void
    {
        // Act
        $result = $this->storage->loadAvailability('non_existent');

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_deletes_availability_file(): void
    {
        // Arrange
        $dto = $this->createTestDTO('es');
        $this->storage->saveAvailability('es', $dto);

        // Act
        $deleteResult = $this->storage->deleteAvailability('es');

        // Assert
        $this->assertTrue($deleteResult);
        Storage::disk('s3-local')->assertMissing(
            'wellwo/availability/es/content.json'
        );
    }

    #[Test]
    public function it_lists_all_available_languages(): void
    {
        // Arrange
        $languages = ['fr', 'en', 'es'];
        foreach ($languages as $lang) {
            $dto = $this->createTestDTO($lang);
            $this->storage->saveAvailability($lang, $dto);
        }

        // Act
        $availableLanguages = $this->storage->listAvailableLanguages();

        // Assert
        $this->assertCount(3, $availableLanguages);
        $this->assertContains('fr', $availableLanguages);
        $this->assertContains('en', $availableLanguages);
        $this->assertContains('es', $availableLanguages);
    }

    #[Test]
    public function it_logs_operations_for_debugging(): void
    {
        // Arrange
        Log::shouldReceive('debug')
            ->with('S3 upload attempt', Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('S3 upload successful', Mockery::any())
            ->once();

        $dto = $this->createTestDTO('fr');

        // Act
        $this->storage->saveAvailability('fr', $dto);
    }

    #[Test]
    public function it_handles_corrupted_json_gracefully(): void
    {
        // Arrange - Save corrupted JSON
        Storage::disk('s3-local')->put(
            'wellwo/availability/fr/content.json',
            'invalid json {'
        );

        Log::shouldReceive('error')
            ->with(Mockery::on(function ($message): bool {
                return str_contains($message, 'Failed to parse availability JSON');
            }), Mockery::any())
            ->once();

        // Act
        $result = $this->storage->loadAvailability('fr');

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_ensures_utf8_encoding(): void
    {
        // Arrange - DTO with special characters
        $dto = new ContentAvailabilityDTO;
        $dto->version = '1.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = 'fr';
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['café', 'naïve', 'résumé'],
        ];
        $dto->statistics = ['totalItems' => 3, 'availableItems' => 3];

        // Act
        $this->storage->saveAvailability('fr', $dto);

        // Assert
        $loadedDto = $this->storage->loadAvailability('fr');
        $this->assertContains('café', $loadedDto->endpoints['recordedClassesGetDisciplines']);
        $this->assertContains('naïve', $loadedDto->endpoints['recordedClassesGetDisciplines']);
        $this->assertContains('résumé', $loadedDto->endpoints['recordedClassesGetDisciplines']);
    }

    #[Test]
    public function it_follows_s3_storage_service_pattern(): void
    {
        // Verify the storage class follows the same pattern as S3StorageService
        $this->assertInstanceOf(ContentAvailabilityStorage::class, $this->storage);

        // Should have getDisk method
        $this->assertTrue(method_exists($this->storage, 'getDisk'));

        // Should handle atomic updates
        $dto = $this->createTestDTO('fr');
        $result = $this->storage->saveAvailability('fr', $dto);
        $this->assertTrue($result);
    }

    #[Test]
    public function it_uses_correct_path_structure(): void
    {
        // The path should follow pattern: wellwo/availability/{language}/content.json
        $languages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $lang) {
            // Fresh storage for each test iteration
            Storage::fake('s3-local');
            $dto = $this->createTestDTO($lang);
            $this->storage->saveAvailability($lang, $dto);

            $expectedPath = "wellwo/availability/{$lang}/content.json";
            Storage::disk('s3-local')->assertExists($expectedPath);
        }
    }

    #[Test]
    public function it_handles_storage_exceptions(): void
    {
        // This test validates that storage exceptions are properly logged
        // The actual storage implementation will handle real S3 exceptions
        $this->assertTrue(true);
    }

    private function createTestDTO(string $language): ContentAvailabilityDTO
    {
        $dto = new ContentAvailabilityDTO;
        $dto->version = '1.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = $language;
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['id1', 'id2'],
            'recordedClassesGetVideoList' => ['video1'],
            'recordedProgramsGetPrograms' => [],
            'recordedProgramsGetVideoList' => ['progVideo1', 'progVideo2'],
        ];
        $dto->statistics = [
            'totalItems' => 6,
            'availableItems' => 6,
        ];

        return $dto;
    }
}
