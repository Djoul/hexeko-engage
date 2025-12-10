<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Contracts;

use App\Integrations\Wellbeing\WellWo\Actions\FilterContentByLanguageAction;
use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JsonException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class FilterContractTest extends TestCase
{
    private FilterContentByLanguageAction $filterAction;

    private MockInterface $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStorage = Mockery::mock(ContentAvailabilityStorage::class);
        $this->filterAction = new FilterContentByLanguageAction(
            $this->mockStorage,
            Config::getFacadeRoot()
        );
    }

    #[Test]
    public function it_returns_all_content_when_feature_disabled(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', false);

        $content = collect([
            ['id' => 'bYVoQZEVVzPo', 'name' => 'GAP'],
            ['id' => 'crossfit123', 'name' => 'CrossFit'],
            ['id' => 'tIcmyY7iq5sa', 'name' => 'Hipopresivos'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals($content->toArray(), $result->toArray());
    }

    #[Test]
    public function it_returns_all_content_when_no_availability_data(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn(null);

        $content = collect([
            ['id' => 'bYVoQZEVVzPo', 'name' => 'GAP'],
            ['id' => 'crossfit123', 'name' => 'CrossFit'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals($content->toArray(), $result->toArray());
    }

    #[Test]
    public function it_filters_content_based_on_availability(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => ['bYVoQZEVVzPo', 'tIcmyY7iq5sa'],
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'bYVoQZEVVzPo', 'name' => 'GAP'],
            ['id' => 'crossfit123', 'name' => 'CrossFit'], // Not available
            ['id' => 'tIcmyY7iq5sa', 'name' => 'Hipopresivos'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('bYVoQZEVVzPo', $result->first()['id']);
        $this->assertEquals('tIcmyY7iq5sa', $result->last()['id']);
        $this->assertNull($result->firstWhere('id', 'crossfit123'));
    }

    #[Test]
    public function it_handles_all_supported_endpoints(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $endpoints = [
            'recordedClassesGetDisciplines',
            'recordedClassesGetVideoList',
            'recordedProgramsGetPrograms',
            'recordedProgramsGetVideoList',
        ];

        // Create DTOs for each endpoint
        $dtos = [];
        foreach ($endpoints as $endpoint) {
            $dto = new ContentAvailabilityDTO;
            $dto->language = 'en';
            $dto->endpoints = [
                $endpoint => ['item1', 'item2'],
            ];
            $dtos[] = $dto;
        }

        // Setup mock to return DTOs in sequence
        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->times(4)
            ->with('en')
            ->andReturn($dtos[0], $dtos[1], $dtos[2], $dtos[3]);

        foreach ($endpoints as $endpoint) {
            // Create a fresh action instance to avoid cache issues
            $freshAction = new FilterContentByLanguageAction(
                $this->mockStorage,
                Config::getFacadeRoot()
            );

            $content = collect([
                ['id' => 'item1'],
                ['id' => 'item2'],
                ['id' => 'item3'], // Not available
            ]);

            // Act
            $result = $freshAction->execute($content, 'en', $endpoint);

            // Assert
            $this->assertCount(2, $result, "Failed for endpoint: {$endpoint}");
            $this->assertEquals('item1', $result->first()['id']);
            $this->assertEquals('item2', $result->last()['id']);
        }
    }

    #[Test]
    public function it_handles_empty_availability_list(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => [], // Empty availability
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'bYVoQZEVVzPo', 'name' => 'GAP'],
            ['id' => 'crossfit123', 'name' => 'CrossFit'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_logs_warning_when_no_availability_data(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        Log::shouldReceive('warning')
            ->once()
            ->with('[recordedClassesGetDisciplines] No availability data for language fr, returning all content');

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn(null);

        $content = collect([['id' => 'test']]);

        // Act
        $this->filterAction->execute($content, 'fr', 'recordedClassesGetDisciplines');
    }

    #[Test]
    public function it_logs_error_on_corrupted_data(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andThrow(new JsonException('Invalid JSON'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message): bool {
                return str_contains($message, 'Failed to load availability data');
            });

        // Also expect the warning after error
        Log::shouldReceive('warning')
            ->once()
            ->with('[recordedClassesGetDisciplines] No availability data for language fr, returning all content');

        $content = collect([['id' => 'test']]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert - Should return all content as fallback
        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_supports_all_required_languages(): void
    {
        // Arrange
        $supportedLanguages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        Config::set('services.wellwo.filter_by_language', true);

        foreach ($supportedLanguages as $language) {
            $availabilityDto = new ContentAvailabilityDTO;
            $availabilityDto->language = $language;
            $availabilityDto->endpoints = [
                'recordedClassesGetDisciplines' => ['test-id'],
            ];

            $this->mockStorage
                ->shouldReceive('loadAvailability')
                ->once()
                ->with($language)
                ->andReturn($availabilityDto);

            $content = collect([
                ['id' => 'test-id'],
                ['id' => 'other-id'],
            ]);

            // Act
            $result = $this->filterAction->execute(
                $content,
                $language,
                'recordedClassesGetDisciplines'
            );

            // Assert
            $this->assertCount(1, $result);
            $this->assertEquals('test-id', $result->first()['id']);
        }
    }

    #[Test]
    public function it_preserves_original_collection_structure(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => ['id1', 'id3'],
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'id1', 'name' => 'Class 1', 'extra' => 'data1'],
            ['id' => 'id2', 'name' => 'Class 2', 'extra' => 'data2'],
            ['id' => 'id3', 'name' => 'Class 3', 'extra' => 'data3'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetDisciplines'
        );

        // Assert
        $this->assertCount(2, $result);

        $firstItem = $result->first();
        $this->assertEquals('id1', $firstItem['id']);
        $this->assertEquals('Class 1', $firstItem['name']);
        $this->assertEquals('data1', $firstItem['extra']);

        $lastItem = $result->last();
        $this->assertEquals('id3', $lastItem['id']);
        $this->assertEquals('Class 3', $lastItem['name']);
        $this->assertEquals('data3', $lastItem['extra']);
    }

    #[Test]
    public function it_handles_nested_video_structure(): void
    {
        // Arrange for videos which might have nested structure
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetVideoList' => ['video1', 'video3'],
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn($availabilityDto);

        // For nested structures, pass the actual items array to be filtered
        $content = collect([
            ['id' => 'video1', 'title' => 'Video 1'],
            ['id' => 'video2', 'title' => 'Video 2'],
            ['id' => 'video3', 'title' => 'Video 3'],
        ]);

        // Act
        $result = $this->filterAction->execute(
            $content,
            'fr',
            'recordedClassesGetVideoList'
        );

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('video1', $result->first()['id']);
        $this->assertEquals('video3', $result->last()['id']);
    }
}
