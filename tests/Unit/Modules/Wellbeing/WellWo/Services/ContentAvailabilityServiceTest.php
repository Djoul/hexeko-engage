<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Services;

use App\Integrations\Wellbeing\WellWo\Services\ContentAvailabilityService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
class ContentAvailabilityServiceTest extends TestCase
{
    private ContentAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContentAvailabilityService;
    }

    #[Test]
    public function it_validates_supported_languages(): void
    {
        // Arrange
        $validLanguages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];
        $invalidLanguages = ['de', 'nl', 'ru', 'zh'];

        // Act & Assert - Valid languages
        foreach ($validLanguages as $lang) {
            $this->assertTrue($this->service->isValidLanguage($lang));
        }

        // Act & Assert - Invalid languages
        foreach ($invalidLanguages as $lang) {
            $this->assertFalse($this->service->isValidLanguage($lang));
        }
    }

    #[Test]
    public function it_provides_list_of_supported_languages(): void
    {
        // Act
        $languages = $this->service->getSupportedLanguages();

        // Assert
        $this->assertIsArray($languages);
        $this->assertCount(7, $languages);
        $this->assertContains('es', $languages);
        $this->assertContains('en', $languages);
        $this->assertContains('fr', $languages);
        $this->assertContains('it', $languages);
        $this->assertContains('pt', $languages);
        $this->assertContains('ca', $languages);
        $this->assertContains('mx', $languages);
    }

    #[Test]
    public function it_extracts_ids_from_flat_collection(): void
    {
        // Arrange
        $collection = collect([
            ['id' => 'id1', 'name' => 'Item 1'],
            ['id' => 'id2', 'name' => 'Item 2'],
            ['id' => 'id3', 'name' => 'Item 3'],
        ]);

        // Act
        $ids = $this->service->extractContentIds($collection);

        // Assert
        $this->assertIsArray($ids);
        $this->assertCount(3, $ids);
        $this->assertContains('id1', $ids);
        $this->assertContains('id2', $ids);
        $this->assertContains('id3', $ids);
    }

    #[Test]
    public function it_extracts_ids_from_nested_media_structure(): void
    {
        // Arrange - Video response structure
        $collection = collect([
            'mediaItems' => [
                ['id' => 'video1', 'title' => 'Video 1'],
                ['id' => 'video2', 'title' => 'Video 2'],
            ],
            'otherData' => 'some value',
        ]);

        // Act
        $ids = $this->service->extractContentIds($collection, 'mediaItems');

        // Assert
        $this->assertIsArray($ids);
        $this->assertCount(2, $ids);
        $this->assertContains('video1', $ids);
        $this->assertContains('video2', $ids);
    }

    #[Test]
    public function it_handles_empty_collections(): void
    {
        // Arrange
        $emptyCollection = collect([]);

        // Act
        $ids = $this->service->extractContentIds($emptyCollection);

        // Assert
        $this->assertIsArray($ids);
        $this->assertEmpty($ids);
    }

    #[Test]
    public function it_handles_missing_media_items_key(): void
    {
        // Arrange
        $collection = collect([
            'otherData' => 'value',
            // No mediaItems key
        ]);

        // Act
        $ids = $this->service->extractContentIds($collection, 'mediaItems');

        // Assert
        $this->assertIsArray($ids);
        $this->assertEmpty($ids);
    }

    #[Test]
    public function it_provides_endpoint_names(): void
    {
        // Act
        $endpoints = $this->service->getWellWoEndpoints();

        // Assert
        $this->assertIsArray($endpoints);
        $this->assertCount(4, $endpoints);
        $this->assertContains('recordedClassesGetDisciplines', $endpoints);
        $this->assertContains('recordedClassesGetVideoList', $endpoints);
        $this->assertContains('recordedProgramsGetPrograms', $endpoints);
        $this->assertContains('recordedProgramsGetVideoList', $endpoints);
    }

    #[Test]
    public function it_generates_cache_key_for_language(): void
    {
        // Act
        $cacheKey = $this->service->getCacheKey('fr');

        // Assert
        $this->assertIsString($cacheKey);
        $this->assertStringContainsString('wellwo', $cacheKey);
        $this->assertStringContainsString('availability', $cacheKey);
        $this->assertStringContainsString('fr', $cacheKey);
    }

    #[Test]
    public function it_provides_different_cache_keys_for_different_languages(): void
    {
        // Act
        $cacheKeyFr = $this->service->getCacheKey('fr');
        $cacheKeyEn = $this->service->getCacheKey('en');

        // Assert
        $this->assertNotEquals($cacheKeyFr, $cacheKeyEn);
    }

    #[Test]
    public function it_determines_if_endpoint_contains_videos(): void
    {
        // Act & Assert
        $this->assertFalse($this->service->isVideoEndpoint('recordedClassesGetDisciplines'));
        $this->assertTrue($this->service->isVideoEndpoint('recordedClassesGetVideoList'));
        $this->assertFalse($this->service->isVideoEndpoint('recordedProgramsGetPrograms'));
        $this->assertTrue($this->service->isVideoEndpoint('recordedProgramsGetVideoList'));
    }

    #[Test]
    public function it_provides_cache_ttl_configuration(): void
    {
        // Act
        $ttl = $this->service->getCacheTtl();

        // Assert
        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
        // Default should be reasonable (e.g., 5 minutes)
        $this->assertGreaterThanOrEqual(300, $ttl);
    }

    #[Test]
    public function it_normalizes_language_codes(): void
    {
        // Act - Should handle case variations
        $this->assertEquals('fr', $this->service->normalizeLanguageCode('FR'));
        $this->assertEquals('en', $this->service->normalizeLanguageCode('EN'));
        $this->assertEquals('es', $this->service->normalizeLanguageCode('Es'));
        $this->assertEquals('mx', $this->service->normalizeLanguageCode('MX'));
    }

    #[Test]
    public function it_provides_default_language_when_invalid(): void
    {
        // Act
        $defaultLang = $this->service->getDefaultLanguage();

        // Assert
        $this->assertIsString($defaultLang);
        $this->assertTrue($this->service->isValidLanguage($defaultLang));
    }
}
