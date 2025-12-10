<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Actions\FilterContentByLanguageAction;
use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class FilterContentByLanguageActionTest extends TestCase
{
    private FilterContentByLanguageAction $action;

    private MockInterface $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStorage = Mockery::mock(ContentAvailabilityStorage::class);
        $this->action = new FilterContentByLanguageAction(
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
            ['id' => 'item1', 'name' => 'Item 1'],
            ['id' => 'item2', 'name' => 'Item 2'],
            ['id' => 'item3', 'name' => 'Item 3'],
        ]);

        // The storage should not be called when feature is disabled
        $this->mockStorage
            ->shouldNotReceive('loadAvailability');

        // Act
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetDisciplines');

        // Assert
        $this->assertEquals($content->count(), $result->count());
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

        Log::shouldReceive('warning')
            ->once()
            ->with('[recordedClassesGetDisciplines] No availability data for language fr, returning all content');

        $content = collect([
            ['id' => 'item1', 'name' => 'Item 1'],
            ['id' => 'item2', 'name' => 'Item 2'],
        ]);

        // Act
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetDisciplines');

        // Assert
        $this->assertEquals($content->count(), $result->count());
        $this->assertEquals($content->toArray(), $result->toArray());
    }

    #[Test]
    public function it_filters_content_based_on_available_ids(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => ['item1', 'item3'], // Only item1 and item3 are available
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->with('fr')
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'item1', 'name' => 'Item 1'],
            ['id' => 'item2', 'name' => 'Item 2'], // This should be filtered out
            ['id' => 'item3', 'name' => 'Item 3'],
            ['id' => 'item4', 'name' => 'Item 4'],  // This should be filtered out
        ]);

        // Act
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetDisciplines');

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('item1', $result->values()[0]['id']);
        $this->assertEquals('item3', $result->values()[1]['id']);
        $this->assertNull($result->firstWhere('id', 'item2'));
        $this->assertNull($result->firstWhere('id', 'item4'));
    }

    #[Test]
    public function it_returns_empty_collection_when_no_items_available(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => [], // No items available
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'item1', 'name' => 'Item 1'],
            ['id' => 'item2', 'name' => 'Item 2'],
        ]);

        // Act
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetDisciplines');

        // Assert
        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_endpoint_not_in_availability_data(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => ['item1'],
            // Note: recordedClassesGetVideoList is not present
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->andReturn($availabilityDto);

        $content = collect([
            ['id' => 'video1', 'title' => 'Video 1'],
            ['id' => 'video2', 'title' => 'Video 2'],
        ]);

        // Act - Try to filter for an endpoint not in availability data
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetVideoList');

        // Assert - Should return empty as endpoint has no data
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function it_preserves_item_structure_after_filtering(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'en';
        $availabilityDto->endpoints = [
            'recordedProgramsGetPrograms' => ['prog1', 'prog3'],
        ];

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->andReturn($availabilityDto);

        $content = collect([
            [
                'id' => 'prog1',
                'title' => 'Program 1',
                'description' => 'Description 1',
                'metadata' => ['level' => 'beginner'],
            ],
            [
                'id' => 'prog2',
                'title' => 'Program 2',
                'description' => 'Description 2',
                'metadata' => ['level' => 'intermediate'],
            ],
            [
                'id' => 'prog3',
                'title' => 'Program 3',
                'description' => 'Description 3',
                'metadata' => ['level' => 'advanced'],
            ],
        ]);

        // Act
        $result = $this->action->execute($content, 'en', 'recordedProgramsGetPrograms');

        // Assert
        $this->assertCount(2, $result);

        $firstItem = $result->firstWhere('id', 'prog1');
        $this->assertEquals('Program 1', $firstItem['title']);
        $this->assertEquals('Description 1', $firstItem['description']);
        $this->assertEquals('beginner', $firstItem['metadata']['level']);

        $secondItem = $result->firstWhere('id', 'prog3');
        $this->assertEquals('Program 3', $secondItem['title']);
        $this->assertEquals('Description 3', $secondItem['description']);
        $this->assertEquals('advanced', $secondItem['metadata']['level']);
    }

    #[Test]
    public function it_handles_storage_errors_gracefully(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->once()
            ->andThrow(new Exception('Storage connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return str_contains($message, '[recordedClassesGetDisciplines]') &&
                       str_contains($message, 'Failed to load availability data') &&
                       str_contains($context['error'], 'Storage connection failed');
            });

        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::type('string'));

        $content = collect([
            ['id' => 'item1', 'name' => 'Item 1'],
        ]);

        // Act
        $result = $this->action->execute($content, 'fr', 'recordedClassesGetDisciplines');

        // Assert - Should return all content as fallback
        $this->assertEquals($content->count(), $result->count());
        $this->assertEquals($content->toArray(), $result->toArray());
    }

    #[Test]
    public function it_loads_availability_data_on_each_request(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $availabilityDto = new ContentAvailabilityDTO;
        $availabilityDto->language = 'fr';
        $availabilityDto->endpoints = [
            'recordedClassesGetDisciplines' => ['item1'],
        ];

        // Without cache, storage should be called for each execute() call
        $this->mockStorage
            ->shouldReceive('loadAvailability')
            ->twice() // Called twice now that cache is removed
            ->with('fr')
            ->andReturn($availabilityDto);

        $content1 = collect([['id' => 'item1'], ['id' => 'item2']]);
        $content2 = collect([['id' => 'item1'], ['id' => 'item3']]);

        // Act - Call filter multiple times with same language
        $result1 = $this->action->execute($content1, 'fr', 'recordedClassesGetDisciplines');
        $result2 = $this->action->execute($content2, 'fr', 'recordedClassesGetDisciplines');

        // Assert
        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
    }

    #[Test]
    public function it_supports_all_required_languages(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $languages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $lang) {
            $availabilityDto = new ContentAvailabilityDTO;
            $availabilityDto->language = $lang;
            $availabilityDto->endpoints = [
                'recordedClassesGetDisciplines' => ['item1'],
            ];

            $this->mockStorage
                ->shouldReceive('loadAvailability')
                ->with($lang)
                ->andReturn($availabilityDto);

            $content = collect([
                ['id' => 'item1'],
                ['id' => 'item2'],
            ]);

            // Act
            $result = $this->action->execute($content, $lang, 'recordedClassesGetDisciplines');

            // Assert
            $this->assertCount(1, $result);
            $this->assertEquals('item1', $result->first()['id']);
        }
    }

    #[Test]
    public function it_filters_all_four_endpoint_types(): void
    {
        // Arrange
        Config::set('services.wellwo.filter_by_language', true);

        $endpoints = [
            'recordedClassesGetDisciplines',
            'recordedClassesGetVideoList',
            'recordedProgramsGetPrograms',
            'recordedProgramsGetVideoList',
        ];

        foreach ($endpoints as $endpoint) {
            // Create a fresh mock storage for each iteration
            $mockStorage = Mockery::mock(ContentAvailabilityStorage::class);
            $mockConfig = Mockery::mock(Repository::class);

            $mockConfig->shouldReceive('get')
                ->with('services.wellwo.filter_by_language', false)
                ->andReturn(true);

            // Create a new action instance for each endpoint to avoid caching issues
            $action = new FilterContentByLanguageAction($mockStorage, $mockConfig);

            $availabilityDto = new ContentAvailabilityDTO;
            $availabilityDto->language = 'en';
            $availabilityDto->endpoints = [
                $endpoint => ['available1', 'available2'],
            ];

            $mockStorage
                ->shouldReceive('loadAvailability')
                ->once()
                ->with('en')
                ->andReturn($availabilityDto);

            $content = collect([
                ['id' => 'available1'],
                ['id' => 'notAvailable'],
                ['id' => 'available2'],
            ]);

            // Act
            $result = $action->execute($content, 'en', $endpoint);

            // Assert
            $this->assertCount(2, $result);
            $this->assertTrue($result->contains('id', 'available1'));
            $this->assertTrue($result->contains('id', 'available2'));
            $this->assertFalse($result->contains('id', 'notAvailable'));
        }
    }
}
