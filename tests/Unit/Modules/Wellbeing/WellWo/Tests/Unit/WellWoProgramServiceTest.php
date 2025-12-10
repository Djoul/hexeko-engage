<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Tests\Unit;

use App\Integrations\Wellbeing\WellWo\DTOs\VideoDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use App\Integrations\Wellbeing\WellWo\Services\WellWoApiService;
use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WellWoProgramServiceTest extends TestCase
{
    private WellWoProgramService $service;

    /** @var MockInterface&WellWoApiService */
    private MockInterface $apiService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var MockInterface&WellWoApiService $mockApiService */
        $mockApiService = Mockery::mock(WellWoApiService::class);
        $this->apiService = $mockApiService;
        $this->service = new WellWoProgramService($mockApiService);

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_fetches_programs_list(): void
    {
        // Arrange
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'program1',
                'name' => 'Program 1',
                'image' => 'https://example.com/image1.jpg',
            ],
            '1' => [
                'id' => 'program2',
                'name' => 'Program 2',
                'image' => 'https://example.com/image2.jpg',
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'es'])
            ->andReturn($apiResponse);

        // Act
        $programs = $this->service->getPrograms('es');

        // Assert
        $this->assertInstanceOf(Collection::class, $programs);
        $this->assertCount(2, $programs);
        $this->assertContainsOnlyInstancesOf(WellWoDTO::class, $programs);
        /** @var WellWoDTO $firstProgram */
        $firstProgram = $programs->first();
        $this->assertEquals('program1', $firstProgram->id);
        $this->assertEquals('Program 1', $firstProgram->name);
    }

    #[Test]
    public function it_calls_api_on_each_request_without_cache(): void
    {
        // Arrange
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'program1',
                'name' => 'Program 1',
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->twice() // Cache is disabled, should be called twice
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'es'])
            ->andReturn($apiResponse);

        // Act
        $programs1 = $this->service->getPrograms('es');
        $programs2 = $this->service->getPrograms('es');

        // Assert
        $this->assertEquals($programs1, $programs2);
    }

    #[Test]
    public function it_returns_empty_collection_on_api_failure(): void
    {
        // Arrange
        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->andThrow(new WellWoApiException('API Error'));

        // Act
        $programs = $this->service->getPrograms();

        // Assert
        $this->assertInstanceOf(Collection::class, $programs);
        $this->assertCount(0, $programs);
    }

    #[Test]
    public function it_supports_language_parameter(): void
    {
        // Arrange
        $apiResponse = ['status' => 'OK'];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'fr'])
            ->andReturn($apiResponse);

        // Act
        $this->service->getPrograms('fr');

        // Assert - expectations are in the mock
    }

    #[Test]
    public function it_gets_videos_for_program_by_id(): void
    {
        // Arrange
        $programId = 'program1';
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'video1',
                'name' => 'Video 1',
                'image' => 'https://example.com/video1.jpg',
                'video' => 'https://example.com/video1.mp4',
                'length' => '10:00',
            ],
            '1' => [
                'id' => 'video2',
                'name' => 'Video 2',
                'image' => 'https://example.com/video2.jpg',
                'video' => 'https://example.com/video2.mp4',
                'length' => '15:00',
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetVideoList', 'id' => $programId, 'lang' => 'es'])
            ->andReturn($apiResponse);

        // Act
        $videos = $this->service->getProgramVideoById($programId, 'es');

        // Assert
        $this->assertIsArray($videos);
        $this->assertCount(2, $videos);
        $this->assertContainsOnlyInstancesOf(VideoDTO::class, $videos);
        $this->assertEquals('video1', $videos[0]->id);
        $this->assertEquals('Video 1', $videos[0]->name);
    }

    #[Test]
    public function it_returns_null_when_program_not_found(): void
    {
        // Arrange
        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->andThrow(WellWoApiException::apiError('Not found', 404));

        // Act
        $videos = $this->service->getProgramVideoById('nonexistent');

        // Assert
        $this->assertNull($videos);
    }

    #[Test]
    public function it_makes_separate_calls_for_different_languages(): void
    {
        // Arrange
        $apiResponseEs = [
            'status' => 'OK',
            '0' => ['id' => 'p1', 'name' => 'Programa 1'],
        ];

        $apiResponseEn = [
            'status' => 'OK',
            '0' => ['id' => 'p1', 'name' => 'Program 1'],
        ];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'es'])
            ->andReturn($apiResponseEs);

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'en'])
            ->andReturn($apiResponseEn);

        // Act
        $programsEs = $this->service->getPrograms('es');
        $programsEn = $this->service->getPrograms('en');

        // Assert
        /** @var WellWoDTO $firstProgramEs */
        $firstProgramEs = $programsEs->first();
        /** @var WellWoDTO $firstProgramEn */
        $firstProgramEn = $programsEn->first();
        $this->assertEquals('Programa 1', $firstProgramEs->name);
        $this->assertEquals('Program 1', $firstProgramEn->name);
    }

    #[Test]
    public function it_calls_api_with_force_refresh_parameter(): void
    {
        // Arrange
        $apiResponse1 = [
            'status' => 'OK',
            '0' => ['id' => 'p1', 'name' => 'Old Name'],
        ];

        $apiResponse2 = [
            'status' => 'OK',
            '0' => ['id' => 'p1', 'name' => 'New Name'],
        ];

        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'es'])
            ->andReturn($apiResponse1);

        // First call
        $programs1 = $this->service->getPrograms('es');

        // Setup for second call (cache disabled, so API called again)
        /** @phpstan-ignore-next-line */
        $this->apiService
            ->shouldReceive('post')
            ->once()
            ->with(['op' => 'healthyProgramsGetList', 'lang' => 'es'])
            ->andReturn($apiResponse2);

        // Act - Force refresh parameter (has no effect since cache is disabled)
        $programs2 = $this->service->getPrograms('es', true);

        // Assert
        /** @var WellWoDTO $firstProgram1 */
        $firstProgram1 = $programs1->first();
        /** @var WellWoDTO $firstProgram2 */
        $firstProgram2 = $programs2->first();
        $this->assertEquals('Old Name', $firstProgram1->name);
        $this->assertEquals('New Name', $firstProgram2->name);
    }
}
