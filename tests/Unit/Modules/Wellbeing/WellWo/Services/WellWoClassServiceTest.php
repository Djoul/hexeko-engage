<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Services;

use App\Integrations\Wellbeing\WellWo\DTOs\ClassVideoDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use App\Integrations\Wellbeing\WellWo\Services\WellWoApiService;
use App\Integrations\Wellbeing\WellWo\Services\WellWoClassService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[Group('wellwo')]
class WellWoClassServiceTest extends TestCase
{
    private WellWoClassService $service;

    private MockObject $apiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiService = $this->createMock(WellWoApiService::class);
        $this->service = new WellWoClassService($this->apiService);

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_gets_classes(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'class1',
                'name' => 'Yoga Class',
                'description' => 'Relaxing yoga',
                'duration' => 60,
            ],
            '1' => [
                'id' => 'class2',
                'name' => 'Pilates Class',
                'description' => 'Core strengthening',
                'duration' => 45,
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'recordedClassesGetDisciplines',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getClasses('en');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        $firstClass = $result->first();
        $this->assertInstanceOf(WellWoDTO::class, $firstClass);
        $this->assertEquals('class1', $firstClass->id);
        $this->assertEquals('Yoga Class', $firstClass->name);
    }

    #[Test]
    public function it_uses_default_language_when_not_specified(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'class1',
                'name' => 'Clase de Yoga',
                'description' => 'Yoga relajante',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'es', // Default language
                'op' => 'recordedClassesGetDisciplines',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getClasses();

        $this->assertCount(1, $result);
        $this->assertEquals('Clase de Yoga', $result->first()->name);
    }

    #[Test]
    public function it_gets_class_video_by_id(): void
    {
        $apiResponse = [
            'status' => 'OK',
            'mediaItems' => [
                [
                    'name' => 'Yoga Session 1',
                    'description' => 'First yoga session',
                    'url' => 'https://example.com/video1.mp4',
                    'level' => 'beginner',
                ],
                [
                    'name' => 'Yoga Session 2',
                    'description' => 'Second yoga session',
                    'url' => 'https://example.com/video2.mp4',
                    'level' => 'intermediate',
                ],
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'recordedClassesGetVideoList',
                'id' => 'class1',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getClasseVideoById('class1', 'en');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $firstVideo = $result[0];
        $this->assertInstanceOf(ClassVideoDTO::class, $firstVideo);
        $this->assertEquals('Yoga Session 1', $firstVideo->name);
        $this->assertEquals('First yoga session', $firstVideo->description);
    }

    #[Test]
    public function it_returns_null_when_class_video_not_found(): void
    {
        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'recordedClassesGetVideoList',
                'id' => 'nonexistent',
            ])
            ->willThrowException(new WellWoApiException('Not found', 404));

        $result = $this->service->getClasseVideoById('nonexistent', 'en');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_classes(): void
    {
        $apiResponse = [
            'status' => 'OK',
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $result = $this->service->getClasses('en');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_api_error_when_fetching_classes(): void
    {
        $this->apiService->expects($this->once())
            ->method('post')
            ->willThrowException(WellWoApiException::apiError('API error'));

        // The service catches exceptions and returns empty collection
        $result = $this->service->getClasses('en');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_filters_out_status_key_from_response(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'class1',
                'name' => 'Class 1',
            ],
            'error' => 'This should be filtered',
            '1' => [
                'id' => 'class2',
                'name' => 'Class 2',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $result = $this->service->getClasses('en');

        // Should only have the numeric keys (classes)
        $this->assertCount(2, $result);
        $this->assertEquals('class1', $result->first()->id);
        $this->assertEquals('class2', $result->last()->id);
    }

    #[Test]
    public function it_implements_third_party_service_interface(): void
    {
        $this->assertEquals('wellwo', $this->service->getProviderName());
        $this->assertEquals('v1', $this->service->getApiVersion());
    }

    #[Test]
    public function it_checks_health_by_fetching_videos(): void
    {
        $this->apiService->method('post')
            ->with([
                'op' => 'healthyProgramsGetVideoList',
                'lang' => 'es',
                'id' => 'test',
            ])
            ->willReturn(['status' => 'OK']);

        $result = $this->service->isHealthy();

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_health_on_api_error(): void
    {
        $this->apiService->method('post')
            ->willThrowException(new Exception('API error'));

        $result = $this->service->isHealthy();

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_false_health_when_no_status(): void
    {
        $this->apiService->method('post')
            ->with([
                'op' => 'healthyProgramsGetVideoList',
                'lang' => 'es',
                'id' => 'test',
            ])
            ->willReturn([]);

        $result = $this->service->isHealthy();

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_false_health_on_ko_status(): void
    {
        $this->apiService->method('post')
            ->with([
                'op' => 'healthyProgramsGetVideoList',
                'lang' => 'es',
                'id' => 'test',
            ])
            ->willReturn(['status' => 'KO']);

        $result = $this->service->isHealthy();

        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_api_error_when_fetching_class_videos(): void
    {
        $this->apiService->expects($this->once())
            ->method('post')
            ->willThrowException(WellWoApiException::apiError('API error'));

        $result = $this->service->getClasseVideoById('class1', 'en');

        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_missing_media_items_key_in_response(): void
    {
        // API response without mediaItems key (the bug scenario)
        $apiResponse = [
            'status' => 'KO',
            'message' => 'empty query',
            // No 'mediaItems' key present
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'recordedClassesGetVideoList',
                'id' => 'class1',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getClasseVideoById('class1', 'en');

        // Should return empty array instead of causing error
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_empty_media_items_array(): void
    {
        $apiResponse = [
            'status' => 'OK',
            'mediaItems' => [], // Empty array
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'recordedClassesGetVideoList',
                'id' => 'class1',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getClasseVideoById('class1', 'en');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
