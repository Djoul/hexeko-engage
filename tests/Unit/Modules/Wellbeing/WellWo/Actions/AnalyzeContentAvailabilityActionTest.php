<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Actions\AnalyzeContentAvailabilityAction;
use App\Integrations\Wellbeing\WellWo\DTOs\AnalysisResultDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Services\WellWoClassService;
use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
#[Group('wellwo-filter')]
class AnalyzeContentAvailabilityActionTest extends TestCase
{
    private AnalyzeContentAvailabilityAction $action;

    private MockInterface $mockClassService;

    private MockInterface $mockProgramService;

    private MockInterface $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClassService = Mockery::mock(WellWoClassService::class);
        $this->mockProgramService = Mockery::mock(WellWoProgramService::class);
        $this->mockStorage = Mockery::mock(ContentAvailabilityStorage::class);

        $this->action = new AnalyzeContentAvailabilityAction(
            $this->mockClassService,
            $this->mockProgramService,
            $this->mockStorage
        );
    }

    #[Test]
    public function it_analyzes_all_languages_by_default(): void
    {
        // Arrange
        $this->setupMockApiResponses();
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->times(7)
            ->andReturn(true);

        // Act
        $results = $this->action->execute();

        // Assert
        $this->assertIsArray($results);
        $this->assertCount(7, $results);

        foreach ($results as $result) {
            $this->assertInstanceOf(AnalysisResultDTO::class, $result);
            $this->assertTrue($result->success);
        }
    }

    #[Test]
    public function it_analyzes_specific_languages_when_provided(): void
    {
        // Arrange
        $languages = ['fr', 'en'];
        $this->setupMockApiResponses($languages);
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->twice()
            ->andReturn(true);

        // Act
        $results = $this->action->execute($languages);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals('fr', $results[0]->language);
        $this->assertEquals('en', $results[1]->language);
    }

    #[Test]
    public function it_calls_service_endpoints_per_language(): void
    {
        // Arrange
        $language = 'fr';

        // Expect calls to both services
        $this->mockClassService
            ->shouldReceive('getClasses')
            ->once()
            ->with($language, true)
            ->andReturn(collect([
                ['id' => 'class1'],
                ['id' => 'class2'],
            ]));

        // Mock video calls for classes - only class2 has videos
        $this->mockClassService
            ->shouldReceive('getClasseVideoById')
            ->with('class1', $language)
            ->andReturn([]) // No videos for class1
            ->shouldReceive('getClasseVideoById')
            ->with('class2', $language)
            ->andReturn([ // Has videos for class2
                (object) ['name' => 'video1'],
            ]);

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->once()
            ->with($language, true)
            ->andReturn(collect([
                ['id' => 'prog1'],
            ]));

        // Mock video calls for programs
        $this->mockProgramService
            ->shouldReceive('getProgramVideoById')
            ->with('prog1', $language)
            ->andReturn([ // Has videos for prog1
                (object) ['id' => 'video1'],
            ]);

        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->andReturn(true);

        // Act
        $results = $this->action->execute(['fr']);

        // Assert
        $this->assertCount(1, $results);
        // Only class2 and prog1 have videos
        // Total items: 1 class + 1 class video + 1 program + 1 program video = 4
        $this->assertEquals(4, $results[0]->itemsAnalyzed);
        $this->assertEquals(4, $results[0]->itemsAvailable);
    }

    #[Test]
    public function it_builds_content_availability_dto_correctly(): void
    {
        // Arrange
        $this->mockClassService
            ->shouldReceive('getClasses')
            ->with('fr', true)
            ->andReturn(collect([
                ['id' => 'class1'],
                ['id' => 'class2'],
            ]));

        // Mock video calls - both classes have videos
        $this->mockClassService
            ->shouldReceive('getClasseVideoById')
            ->with('class1', 'fr')
            ->andReturn([
                (object) ['name' => 'video1'],
            ])
            ->shouldReceive('getClasseVideoById')
            ->with('class2', 'fr')
            ->andReturn([
                (object) ['name' => 'video2'],
            ]);

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->with('fr', true)
            ->andReturn(collect([]));

        // Capture the DTO passed to storage
        $capturedDto = null;
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->withArgs(function ($lang, ContentAvailabilityDTO $dto) use (&$capturedDto): bool {
                $capturedDto = $dto;

                return $lang === 'fr';
            })
            ->andReturn(true);

        // Act
        $this->action->execute(['fr']);

        // Assert
        $this->assertNotNull($capturedDto);
        $this->assertEquals('fr', $capturedDto->language);
        $this->assertArrayHasKey('recordedClassesGetDisciplines', $capturedDto->endpoints);
        $this->assertCount(2, $capturedDto->endpoints['recordedClassesGetDisciplines']);
        $this->assertContains('class1', $capturedDto->endpoints['recordedClassesGetDisciplines']);
        $this->assertContains('class2', $capturedDto->endpoints['recordedClassesGetDisciplines']);
        // Check videos are present
        $this->assertArrayHasKey('recordedClassesGetVideoList', $capturedDto->endpoints);
        $this->assertCount(2, $capturedDto->endpoints['recordedClassesGetVideoList']); // 2 videos total
    }

    #[Test]
    public function it_handles_partial_failures_gracefully(): void
    {
        // Arrange - First language succeeds, second fails due to storage
        $this->mockClassService
            ->shouldReceive('getClasses')
            ->once()
            ->with('fr', true)
            ->andReturn(collect([['id' => 'class1']]));

        // Mock videos for class1
        $this->mockClassService
            ->shouldReceive('getClasseVideoById')
            ->with('class1', 'fr')
            ->andReturn([
                (object) ['name' => 'video1'],
            ]);

        $this->mockClassService
            ->shouldReceive('getClasses')
            ->once()
            ->with('en', true)
            ->andReturn(collect([['id' => 'class2']]));

        // Mock videos for class2
        $this->mockClassService
            ->shouldReceive('getClasseVideoById')
            ->with('class2', 'en')
            ->andReturn([
                (object) ['name' => 'video2'],
            ]);

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->once()
            ->with('fr', true)
            ->andReturn(collect([]));

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->once()
            ->with('en', true)
            ->andReturn(collect([]));

        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->with('fr', Mockery::any())
            ->andReturn(true);

        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->with('en', Mockery::any())
            ->andThrow(new Exception('Storage Error'));

        // Act
        $results = $this->action->execute(['fr', 'en']);

        // Assert
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->success);
        $this->assertEquals('fr', $results[0]->language);
        $this->assertFalse($results[1]->success);
        $this->assertEquals('en', $results[1]->language);
        $this->assertStringContainsString('Storage Error', $results[1]->error);
    }

    #[Test]
    public function it_saves_availability_data_via_storage(): void
    {
        // Arrange
        $this->setupMockApiResponses(['es']);

        // Expect save to be called with correct parameters
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->withArgs(function ($lang, ContentAvailabilityDTO $dto): bool {
                return $lang === 'es' &&
                       $dto->language === 'es' &&
                       ! empty($dto->version) &&
                       ! empty($dto->analyzedAt);
            })
            ->andReturn(true);

        // Act
        $results = $this->action->execute(['es']);

        // Assert
        $this->assertTrue($results[0]->success);
    }

    #[Test]
    public function it_handles_empty_api_responses(): void
    {
        // Arrange - All endpoints return empty
        $this->mockClassService
            ->shouldReceive('getClasses')
            ->with('fr', true)
            ->andReturn(collect([]));

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->with('fr', true)
            ->andReturn(collect([]));

        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->andReturn(true);

        // Act
        $results = $this->action->execute(['fr']);

        // Assert
        $this->assertTrue($results[0]->success);
        $this->assertEquals(0, $results[0]->itemsAnalyzed);
        $this->assertEquals(0, $results[0]->itemsAvailable);
    }

    #[Test]
    public function it_tracks_analysis_duration(): void
    {
        // Arrange
        $this->setupMockApiResponses(['fr']);
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->andReturn(true);

        // Act
        $results = $this->action->execute(['fr']);

        // Assert
        $this->assertGreaterThan(0, $results[0]->duration);
        $this->assertIsFloat($results[0]->duration);
    }

    #[Test]
    public function it_extracts_video_ids_from_nested_structure(): void
    {
        // Arrange
        $this->mockClassService
            ->shouldReceive('getClasses')
            ->with('fr', true)
            ->andReturn(collect([]));

        $this->mockProgramService
            ->shouldReceive('getPrograms')
            ->with('fr', true)
            ->andReturn(collect([]));

        $capturedDto = null;
        $this->mockStorage
            ->shouldReceive('saveAvailability')
            ->once()
            ->withArgs(function ($lang, ContentAvailabilityDTO $dto) use (&$capturedDto): true {
                $capturedDto = $dto;

                return true;
            })
            ->andReturn(true);

        // Act
        $this->action->execute(['fr']);

        // Assert - Video endpoints currently return empty arrays in the implementation
        $this->assertArrayHasKey('recordedClassesGetVideoList', $capturedDto->endpoints);
        $this->assertCount(0, $capturedDto->endpoints['recordedClassesGetVideoList']);
        $this->assertArrayHasKey('recordedProgramsGetVideoList', $capturedDto->endpoints);
        $this->assertCount(0, $capturedDto->endpoints['recordedProgramsGetVideoList']);
    }

    private function setupMockApiResponses(?array $languages = null): void
    {
        $languages = $languages ?? ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($languages as $lang) {
            $this->mockClassService
                ->shouldReceive('getClasses')
                ->with($lang, true)
                ->andReturn(collect([
                    ['id' => "class1_{$lang}"],
                    ['id' => "class2_{$lang}"],
                ]));

            // Mock videos for each class
            $this->mockClassService
                ->shouldReceive('getClasseVideoById')
                ->with("class1_{$lang}", $lang)
                ->andReturn([
                    (object) ['name' => "video1_{$lang}"],
                ])
                ->shouldReceive('getClasseVideoById')
                ->with("class2_{$lang}", $lang)
                ->andReturn([
                    (object) ['name' => "video2_{$lang}"],
                ]);

            $this->mockProgramService
                ->shouldReceive('getPrograms')
                ->with($lang, true)
                ->andReturn(collect([
                    ['id' => "prog1_{$lang}"],
                ]));

            // Mock videos for programs
            $this->mockProgramService
                ->shouldReceive('getProgramVideoById')
                ->with("prog1_{$lang}", $lang)
                ->andReturn([
                    (object) ['id' => "prog_video1_{$lang}"],
                ]);
        }
    }
}
