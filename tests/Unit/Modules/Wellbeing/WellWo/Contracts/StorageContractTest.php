<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Contracts;

use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class StorageContractTest extends TestCase
{
    private ContentAvailabilityStorage $storage;

    private string $testLanguage = 'fr';

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Storage facade with the correct disk name
        // ContentAvailabilityStorage uses 's3-local' in testing environment
        Storage::fake('s3-local');
        Storage::fake('s3');

        $this->storage = new ContentAvailabilityStorage;
    }

    protected function tearDown(): void
    {
        // Clean up storage state between tests
        Storage::fake('s3-local');
        Storage::fake('s3');

        parent::tearDown();
    }

    #[Test]
    public function it_saves_availability_data_to_correct_path(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->version = '1.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = $this->testLanguage;
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['bYVoQZEVVzPo', 'tIcmyY7iq5sa'],
            'recordedClassesGetVideoList' => ['video1', 'video2'],
        ];
        $dto->statistics = [
            'totalItems' => 4,
            'availableItems' => 4,
        ];

        // Act
        $result = $this->storage->saveAvailability($this->testLanguage, $dto);

        // Assert
        $this->assertTrue($result);
        Storage::disk('s3-local')->assertExists(
            "wellwo/availability/{$this->testLanguage}/content.json"
        );

        $savedContent = Storage::disk('s3-local')->get(
            "wellwo/availability/{$this->testLanguage}/content.json"
        );
        $decodedContent = json_decode($savedContent, true);

        $this->assertEquals('1.0.0', $decodedContent['version']);
        $this->assertEquals($this->testLanguage, $decodedContent['language']);
        $this->assertArrayHasKey('endpoints', $decodedContent);
        $this->assertArrayHasKey('statistics', $decodedContent);
    }

    #[Test]
    public function it_loads_availability_data_from_storage(): void
    {
        // Arrange
        $jsonData = [
            'version' => '1.0.0',
            'analyzedAt' => now()->toIso8601String(),
            'language' => $this->testLanguage,
            'endpoints' => [
                'recordedClassesGetDisciplines' => ['bYVoQZEVVzPo', 'tIcmyY7iq5sa'],
            ],
            'statistics' => [
                'totalItems' => 2,
                'availableItems' => 2,
            ],
        ];

        Storage::disk('s3-local')->put(
            "wellwo/availability/{$this->testLanguage}/content.json",
            json_encode($jsonData)
        );

        // Act
        $dto = $this->storage->loadAvailability($this->testLanguage);

        // Assert
        $this->assertNotNull($dto);
        $this->assertInstanceOf(ContentAvailabilityDTO::class, $dto);
        $this->assertEquals('1.0.0', $dto->version);
        $this->assertEquals($this->testLanguage, $dto->language);
        $this->assertCount(1, $dto->endpoints);
        $this->assertCount(2, $dto->endpoints['recordedClassesGetDisciplines']);
    }

    #[Test]
    public function it_returns_null_when_loading_non_existent_file(): void
    {
        // Act
        $dto = $this->storage->loadAvailability('non_existent');

        // Assert
        $this->assertNull($dto);
    }

    #[Test]
    public function it_deletes_availability_file(): void
    {
        // Arrange
        Storage::disk('s3-local')->put(
            "wellwo/availability/{$this->testLanguage}/content.json",
            json_encode(['test' => 'data'])
        );

        // Act
        $result = $this->storage->deleteAvailability($this->testLanguage);

        // Assert
        $this->assertTrue($result);
        Storage::disk('s3-local')->assertMissing(
            "wellwo/availability/{$this->testLanguage}/content.json"
        );
    }

    #[Test]
    public function it_lists_available_languages(): void
    {
        // Arrange
        Storage::disk('s3-local')->put(
            'wellwo/availability/fr/content.json',
            json_encode(['language' => 'fr'])
        );
        Storage::disk('s3-local')->put(
            'wellwo/availability/en/content.json',
            json_encode(['language' => 'en'])
        );
        Storage::disk('s3-local')->put(
            'wellwo/availability/es/content.json',
            json_encode(['language' => 'es'])
        );

        // Act
        $languages = $this->storage->listAvailableLanguages();

        // Assert
        $this->assertIsArray($languages);
        $this->assertCount(3, $languages);
        $this->assertContains('fr', $languages);
        $this->assertContains('en', $languages);
        $this->assertContains('es', $languages);
    }

    #[Test]
    public function it_implements_atomic_updates(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->version = '1.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = $this->testLanguage;
        $dto->endpoints = ['recordedClassesGetDisciplines' => ['test']];
        $dto->statistics = ['totalItems' => 1, 'availableItems' => 1];

        // Save initial data
        $this->storage->saveAvailability($this->testLanguage, $dto);

        // Update data
        $dto->version = '2.0.0';
        $dto->endpoints = ['recordedClassesGetDisciplines' => ['test', 'test2']];

        // Act - Save updated data
        $result = $this->storage->saveAvailability($this->testLanguage, $dto);

        // Assert
        $this->assertTrue($result);

        $loadedDto = $this->storage->loadAvailability($this->testLanguage);
        $this->assertEquals('2.0.0', $loadedDto->version);
        $this->assertCount(2, $loadedDto->endpoints['recordedClassesGetDisciplines']);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully(): void
    {
        // Arrange - Put invalid JSON
        Storage::disk('s3-local')->put(
            "wellwo/availability/{$this->testLanguage}/content.json",
            'invalid json {'
        );

        // Act
        $dto = $this->storage->loadAvailability($this->testLanguage);

        // Assert
        $this->assertNull($dto);
    }

    #[Test]
    public function it_follows_s3_path_pattern(): void
    {
        // This test verifies the path pattern matches specification
        $languages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $language) {
            $expectedPath = "wellwo/availability/{$language}/content.json";

            // Create a simple DTO for testing
            $dto = new ContentAvailabilityDTO;
            $dto->version = '1.0.0';
            $dto->analyzedAt = now()->toIso8601String();
            $dto->language = $language;
            $dto->endpoints = [];
            $dto->statistics = ['totalItems' => 0, 'availableItems' => 0];

            // Save
            $this->storage->saveAvailability($language, $dto);

            // Assert the file exists at the expected path
            Storage::disk('s3-local')->assertExists($expectedPath);
        }
    }
}
