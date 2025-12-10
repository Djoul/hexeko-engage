<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\DTOs;

use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class ContentAvailabilityDTOTest extends TestCase
{
    #[Test]
    public function it_converts_from_json_correctly(): void
    {
        // Arrange
        $jsonData = [
            'version' => '1.0.0',
            'analyzedAt' => '2025-09-26T10:00:00Z',
            'language' => 'fr',
            'endpoints' => [
                'recordedClassesGetDisciplines' => ['id1', 'id2'],
                'recordedClassesGetVideoList' => ['video1', 'video2', 'video3'],
                'recordedProgramsGetPrograms' => ['prog1'],
                'recordedProgramsGetVideoList' => [],
            ],
            'statistics' => [
                'totalItems' => 100,
                'availableItems' => 85,
                'analysisTime' => 45.5,
            ],
        ];

        // Act
        $dto = ContentAvailabilityDTO::fromJson(json_encode($jsonData));

        // Assert
        $this->assertEquals('1.0.0', $dto->version);
        $this->assertEquals('2025-09-26T10:00:00Z', $dto->analyzedAt);
        $this->assertEquals('fr', $dto->language);
        $this->assertIsArray($dto->endpoints);
        $this->assertCount(4, $dto->endpoints);
        $this->assertContains('id1', $dto->endpoints['recordedClassesGetDisciplines']);
        $this->assertContains('video1', $dto->endpoints['recordedClassesGetVideoList']);
        $this->assertEquals(100, $dto->statistics['totalItems']);
    }

    #[Test]
    public function it_converts_to_json_correctly(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->version = '2.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = 'en';
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['class1', 'class2'],
            'recordedProgramsGetPrograms' => ['program1'],
        ];
        $dto->statistics = [
            'totalItems' => 50,
            'availableItems' => 48,
        ];

        // Act
        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('2.0.0', $decoded['version']);
        $this->assertEquals('en', $decoded['language']);
        $this->assertArrayHasKey('endpoints', $decoded);
        $this->assertArrayHasKey('statistics', $decoded);
        $this->assertCount(2, $decoded['endpoints']['recordedClassesGetDisciplines']);
        $this->assertEquals(50, $decoded['statistics']['totalItems']);
    }

    #[Test]
    public function it_checks_if_has_data_for_endpoint(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['id1', 'id2'],
            'recordedClassesGetVideoList' => [],
            'recordedProgramsGetPrograms' => ['prog1'],
        ];

        // Act & Assert
        $this->assertTrue($dto->hasDataForEndpoint('recordedClassesGetDisciplines'));
        $this->assertFalse($dto->hasDataForEndpoint('recordedClassesGetVideoList'));
        $this->assertTrue($dto->hasDataForEndpoint('recordedProgramsGetPrograms'));
        $this->assertFalse($dto->hasDataForEndpoint('nonExistentEndpoint'));
    }

    #[Test]
    public function it_gets_available_ids_for_endpoint(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['id1', 'id2', 'id3'],
            'recordedClassesGetVideoList' => [],
        ];

        // Act
        $classIds = $dto->getAvailableIds('recordedClassesGetDisciplines');
        $videoIds = $dto->getAvailableIds('recordedClassesGetVideoList');
        $unknownIds = $dto->getAvailableIds('unknownEndpoint');

        // Assert
        $this->assertIsArray($classIds);
        $this->assertCount(3, $classIds);
        $this->assertContains('id1', $classIds);
        $this->assertEmpty($videoIds);
        $this->assertEmpty($unknownIds);
    }

    #[Test]
    public function it_handles_all_wellwo_endpoints(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->endpoints = [
            'recordedClassesGetDisciplines' => ['class1'],
            'recordedClassesGetVideoList' => ['video1', 'video2'],
            'recordedProgramsGetPrograms' => ['prog1', 'prog2', 'prog3'],
            'recordedProgramsGetVideoList' => ['progVideo1'],
        ];

        // Act & Assert
        $this->assertTrue($dto->hasDataForEndpoint('recordedClassesGetDisciplines'));
        $this->assertTrue($dto->hasDataForEndpoint('recordedClassesGetVideoList'));
        $this->assertTrue($dto->hasDataForEndpoint('recordedProgramsGetPrograms'));
        $this->assertTrue($dto->hasDataForEndpoint('recordedProgramsGetVideoList'));

        $this->assertCount(1, $dto->getAvailableIds('recordedClassesGetDisciplines'));
        $this->assertCount(2, $dto->getAvailableIds('recordedClassesGetVideoList'));
        $this->assertCount(3, $dto->getAvailableIds('recordedProgramsGetPrograms'));
        $this->assertCount(1, $dto->getAvailableIds('recordedProgramsGetVideoList'));
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;

        // Act - Try to convert to JSON without required fields
        $json = $dto->toJson();
        $decoded = json_decode($json, true);

        // Assert - Should have structure even with empty/null values
        $this->assertArrayHasKey('version', $decoded);
        $this->assertArrayHasKey('analyzedAt', $decoded);
        $this->assertArrayHasKey('language', $decoded);
        $this->assertArrayHasKey('endpoints', $decoded);
        $this->assertArrayHasKey('statistics', $decoded);
    }

    #[Test]
    public function it_maintains_data_integrity_through_conversion(): void
    {
        // Arrange
        $originalData = [
            'version' => '1.2.3',
            'analyzedAt' => '2025-09-26T15:30:00Z',
            'language' => 'es',
            'endpoints' => [
                'recordedClassesGetDisciplines' => ['a', 'b', 'c'],
                'recordedClassesGetVideoList' => ['1', '2'],
                'recordedProgramsGetPrograms' => [],
                'recordedProgramsGetVideoList' => ['x', 'y', 'z'],
            ],
            'statistics' => [
                'totalItems' => 200,
                'availableItems' => 150,
                'processingTime' => 23.45,
                'errors' => 0,
            ],
        ];

        // Act - Convert JSON -> DTO -> JSON
        $dto = ContentAvailabilityDTO::fromJson(json_encode($originalData));
        $resultJson = $dto->toJson();
        $resultData = json_decode($resultJson, true);

        // Assert
        $this->assertEquals($originalData['version'], $resultData['version']);
        $this->assertEquals($originalData['language'], $resultData['language']);
        $this->assertEquals($originalData['endpoints'], $resultData['endpoints']);
        $this->assertEquals($originalData['statistics'], $resultData['statistics']);
    }

    #[Test]
    public function it_supports_all_seven_languages(): void
    {
        $languages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $language) {
            $dto = new ContentAvailabilityDTO;
            $dto->language = $language;
            $dto->version = '1.0.0';
            $dto->analyzedAt = now()->toIso8601String();
            $dto->endpoints = ['recordedClassesGetDisciplines' => ['test']];
            $dto->statistics = ['totalItems' => 1, 'availableItems' => 1];

            $json = $dto->toJson();
            $decoded = json_decode($json, true);

            $this->assertEquals($language, $decoded['language']);
        }
    }

    #[Test]
    public function it_handles_empty_endpoints_gracefully(): void
    {
        // Arrange
        $dto = new ContentAvailabilityDTO;
        $dto->version = '1.0.0';
        $dto->analyzedAt = now()->toIso8601String();
        $dto->language = 'fr';
        $dto->endpoints = []; // Empty endpoints
        $dto->statistics = ['totalItems' => 0, 'availableItems' => 0];

        // Act
        $hasData = $dto->hasDataForEndpoint('recordedClassesGetDisciplines');
        $ids = $dto->getAvailableIds('recordedClassesGetDisciplines');
        $json = $dto->toJson();

        // Assert
        $this->assertFalse($hasData);
        $this->assertEmpty($ids);
        $this->assertIsString($json);
        $this->assertJson($json);
    }
}
