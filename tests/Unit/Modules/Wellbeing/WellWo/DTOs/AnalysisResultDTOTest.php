<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\DTOs;

use App\Integrations\Wellbeing\WellWo\DTOs\AnalysisResultDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class AnalysisResultDTOTest extends TestCase
{
    #[Test]
    public function it_creates_dto_with_success_state(): void
    {
        // Arrange & Act
        $dto = new AnalysisResultDTO;
        $dto->success = true;
        $dto->language = 'fr';
        $dto->itemsAnalyzed = 150;
        $dto->itemsAvailable = 145;
        $dto->error = null;
        $dto->duration = 23.5;

        // Assert
        $this->assertTrue($dto->success);
        $this->assertEquals('fr', $dto->language);
        $this->assertEquals(150, $dto->itemsAnalyzed);
        $this->assertEquals(145, $dto->itemsAvailable);
        $this->assertNull($dto->error);
        $this->assertEquals(23.5, $dto->duration);
    }

    #[Test]
    public function it_creates_dto_with_failure_state(): void
    {
        // Arrange & Act
        $dto = new AnalysisResultDTO;
        $dto->success = false;
        $dto->language = 'en';
        $dto->itemsAnalyzed = 0;
        $dto->itemsAvailable = 0;
        $dto->error = 'API connection timeout';
        $dto->duration = 30.0;

        // Assert
        $this->assertFalse($dto->success);
        $this->assertEquals('en', $dto->language);
        $this->assertEquals(0, $dto->itemsAnalyzed);
        $this->assertEquals(0, $dto->itemsAvailable);
        $this->assertEquals('API connection timeout', $dto->error);
        $this->assertEquals(30.0, $dto->duration);
    }

    #[Test]
    public function it_supports_all_required_languages(): void
    {
        $languages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $language) {
            $dto = new AnalysisResultDTO;
            $dto->language = $language;
            $dto->success = true;
            $dto->itemsAnalyzed = 10;
            $dto->itemsAvailable = 10;

            $this->assertEquals($language, $dto->language);
        }
    }

    #[Test]
    public function it_handles_partial_success(): void
    {
        // Arrange & Act - Partial success (some items analyzed but with issues)
        $dto = new AnalysisResultDTO;
        $dto->success = true; // Overall success even with some failures
        $dto->language = 'es';
        $dto->itemsAnalyzed = 100;
        $dto->itemsAvailable = 75; // Some items not available
        $dto->error = null; // No fatal error
        $dto->duration = 15.2;

        // Assert
        $this->assertTrue($dto->success);
        $this->assertLessThan($dto->itemsAnalyzed, $dto->itemsAvailable);
    }

    #[Test]
    public function it_provides_meaningful_statistics(): void
    {
        // Arrange
        $dto = new AnalysisResultDTO;
        $dto->success = true;
        $dto->language = 'fr';
        $dto->itemsAnalyzed = 200;
        $dto->itemsAvailable = 180;
        $dto->duration = 45.7;

        // Act - Calculate percentage (this could be a method on the DTO)
        $availabilityPercentage = ($dto->itemsAvailable / $dto->itemsAnalyzed) * 100;

        // Assert
        $this->assertEquals(90.0, $availabilityPercentage);
        $this->assertGreaterThan(0, $dto->duration);
    }

    #[Test]
    public function it_handles_zero_items_scenario(): void
    {
        // Arrange & Act
        $dto = new AnalysisResultDTO;
        $dto->success = true;
        $dto->language = 'it';
        $dto->itemsAnalyzed = 0;
        $dto->itemsAvailable = 0;
        $dto->error = null;
        $dto->duration = 1.2;

        // Assert
        $this->assertTrue($dto->success); // Can be successful even with no items
        $this->assertEquals(0, $dto->itemsAnalyzed);
        $this->assertEquals(0, $dto->itemsAvailable);
    }

    #[Test]
    public function it_tracks_analysis_duration(): void
    {
        // Arrange & Act
        $dto = new AnalysisResultDTO;
        $dto->success = true;
        $dto->language = 'pt';
        $dto->itemsAnalyzed = 50;
        $dto->itemsAvailable = 50;
        $dto->duration = 12.345;

        // Assert
        $this->assertIsFloat($dto->duration);
        $this->assertGreaterThan(0, $dto->duration);
        $this->assertEquals(12.345, $dto->duration);
    }

    #[Test]
    public function it_stores_detailed_error_messages(): void
    {
        // Arrange & Act
        $dto = new AnalysisResultDTO;
        $dto->success = false;
        $dto->language = 'ca';
        $dto->error = 'WellWo API Error: 403 Forbidden - Invalid authentication token';

        // Assert
        $this->assertFalse($dto->success);
        $this->assertStringContainsString('403 Forbidden', $dto->error);
        $this->assertStringContainsString('Invalid authentication token', $dto->error);
    }

    #[Test]
    public function it_can_be_used_for_summary_reporting(): void
    {
        // Arrange - Create multiple results for summary
        $results = [];

        $languages = ['fr', 'en', 'es'];
        foreach ($languages as $index => $language) {
            $dto = new AnalysisResultDTO;
            $dto->success = $index !== 2; // Last one fails
            $dto->language = $language;
            $dto->itemsAnalyzed = 100;
            $dto->itemsAvailable = $index === 2 ? 0 : 90 + $index;
            $dto->error = $index === 2 ? 'Connection failed' : null;
            $dto->duration = 10.5 + $index;

            $results[] = $dto;
        }

        // Act - Generate summary
        $successCount = array_reduce($results, fn ($carry, $item): float|int => $carry + ($item->success ? 1 : 0), 0);
        $failureCount = count($results) - $successCount;
        $totalDuration = array_reduce($results, fn ($carry, $item): float => $carry + $item->duration, 0);
        $totalItems = array_reduce($results, fn ($carry, $item): float|int => $carry + $item->itemsAnalyzed, 0);
        $availableItems = array_reduce($results, fn ($carry, $item): float|int => $carry + $item->itemsAvailable, 0);

        // Assert
        $this->assertEquals(2, $successCount);
        $this->assertEquals(1, $failureCount);
        $this->assertEquals(34.5, $totalDuration);
        $this->assertEquals(300, $totalItems);
        $this->assertEquals(181, $availableItems);
    }

    #[Test]
    public function it_differentiates_error_types(): void
    {
        // Different error scenarios
        $errorScenarios = [
            'API timeout' => 'Request timeout after 30 seconds',
            'Authentication' => 'Invalid WellWo API token',
            'Rate limiting' => 'Rate limit exceeded, retry after 60 seconds',
            'Server error' => '500 Internal Server Error',
            'Network' => 'Could not resolve host: my.wellwo.net',
        ];

        foreach ($errorScenarios as $message) {
            $dto = new AnalysisResultDTO;
            $dto->success = false;
            $dto->language = 'fr';
            $dto->error = $message;

            $this->assertFalse($dto->success);
            $this->assertEquals($message, $dto->error);
        }
    }
}
