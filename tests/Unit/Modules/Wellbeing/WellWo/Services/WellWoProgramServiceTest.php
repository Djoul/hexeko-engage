<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Wellbeing\WellWo\Services;

use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use App\Integrations\Wellbeing\WellWo\Services\WellWoApiService;
use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[Group('wellwo')]

class WellWoProgramServiceTest extends TestCase
{
    private WellWoProgramService $service;

    private MockObject $apiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiService = $this->createMock(WellWoApiService::class);
        $this->service = new WellWoProgramService($this->apiService);

        // Clear cache before each test
        Cache::flush();
    }

    #[Test]
    public function it_lists_programs_in_specified_language(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'prog1',
                'name' => 'Program 1',
                'image' => 'https://example.com/prog1.jpg',
            ],
            '1' => [
                'id' => 'prog2',
                'name' => 'Program 2',
                'image' => 'https://example.com/prog2.jpg',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'en',
                'op' => 'healthyProgramsGetList',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getPrograms('en');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        $firstProgram = $result->first();
        $this->assertInstanceOf(WellWoDTO::class, $firstProgram);
        $this->assertEquals('prog1', $firstProgram->id);
        $this->assertEquals('Program 1', $firstProgram->name);
        $this->assertEquals('https://example.com/prog1.jpg', $firstProgram->image);
    }

    #[Test]
    public function it_uses_default_language_when_not_specified(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'prog1',
                'name' => 'Programa 1',
                'image' => 'https://example.com/prog1.jpg',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->with([
                'lang' => 'es', // Default language
                'op' => 'healthyProgramsGetList',
            ])
            ->willReturn($apiResponse);

        $result = $this->service->getPrograms();

        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_programs(): void
    {
        $apiResponse = [
            'status' => 'OK',
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $result = $this->service->getPrograms('en');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_api_error_when_listing_programs(): void
    {

        $this->apiService->expects($this->once())
            ->method('post')
            ->willThrowException(WellWoApiException::apiError('API error'));

        // The service catches exceptions and returns empty collection
        $result = $this->service->getPrograms('en');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_filters_out_status_key_from_response(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'prog1',
                'name' => 'Program 1',
                'image' => 'https://example.com/prog1.jpg',
            ],
            'error' => 'This should also be filtered',
            '1' => [
                'id' => 'prog2',
                'name' => 'Program 2',
                'image' => 'https://example.com/prog2.jpg',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $result = $this->service->getPrograms('en');

        // Should only have the numeric keys (programs)
        $this->assertCount(2, $result);
        $this->assertEquals('prog1', $result->first()->id);
        $this->assertEquals('prog2', $result->last()->id);
    }

    #[Test]
    public function it_implements_third_party_service_interface(): void
    {
        $this->assertEquals('wellwo', $this->service->getProviderName());
        $this->assertEquals('v1', $this->service->getApiVersion());
    }

    #[Test]
    public function it_checks_health_by_fetching_programs(): void
    {
        $this->apiService->method('post')
            ->with([
                'op' => 'healthyProgramsGetList',
                'lang' => 'es',
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
    public function it_returns_false_health_on_ko_status(): void
    {
        $this->apiService->method('post')
            ->with([
                'op' => 'healthyProgramsGetList',
                'lang' => 'es',
            ])
            ->willReturn(['status' => 'KO']);

        $result = $this->service->isHealthy();

        $this->assertFalse($result);
    }

    #[Test]
    public function it_creates_dto_with_all_properties(): void
    {
        $apiResponse = [
            'status' => 'OK',
            '0' => [
                'id' => 'test-id',
                'name' => 'Test Program',
                'image' => 'https://example.com/test.jpg',
                'description' => 'Optional description',
            ],
        ];

        $this->apiService->expects($this->once())
            ->method('post')
            ->willReturn($apiResponse);

        $result = $this->service->getPrograms('en');
        $program = $result->first();

        $this->assertEquals('test-id', $program->id);
        $this->assertEquals('Test Program', $program->name);
        $this->assertEquals('https://example.com/test.jpg', $program->image);
        $this->assertEquals('Optional description', $program->description);
    }
}
