<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Wellbeing\WellWo\Http\V1;

use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

class WellWoProxyControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected bool $checkAuth = false; // Skip auth for initial tests

    protected bool $checkPermissions = false;

    #[Test]
    public function it_returns_programs_list_when_authenticated(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        /** @var MockInterface&WellWoProgramService $mockService */
        $mockService = Mockery::mock(WellWoProgramService::class);
        /** @phpstan-ignore-next-line */
        $mockService->shouldReceive('getPrograms')
            ->once()
            ->with('en')
            ->andReturn(collect([
                new WellWoDTO('p1', 'Program 1', 'image1.jpg'),
                new WellWoDTO('p2', 'Program 2', 'image2.jpg'),
            ]));

        $this->app->instance(WellWoProgramService::class, $mockService);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'image'],
                ],
            ])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_validates_language_parameter(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=invalid');

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lang']);
    }

    #[Test]
    public function it_accepts_valid_language_codes(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        /** @var MockInterface&WellWoProgramService $mockService */
        $mockService = Mockery::mock(WellWoProgramService::class);
        /** @phpstan-ignore-next-line */
        $mockService->shouldReceive('getPrograms')
            ->once()
            ->with('fr')
            ->andReturn(collect());

        $this->app->instance(WellWoProgramService::class, $mockService);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=fr');

        // Assert
        $response->assertOk();
    }

    #[Test]
    public function it_returns_videos_for_valid_program(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $programId = 'test-program';

        /** @var MockInterface&WellWoProgramService $mockProgramService */
        $mockProgramService = Mockery::mock(WellWoProgramService::class);
        /** @phpstan-ignore-next-line */
        $mockProgramService->shouldReceive('getProgramVideoById')
            ->once()
            ->with($programId, 'en')
            ->andReturn([
                'id' => $programId,
                'name' => 'Test Program',
                'image' => 'image.jpg',
                'status' => 'OK',
                'mediaItems' => [
                    ['id' => 'v1', 'name' => 'Video 1', 'image' => 'image1.jpg', 'video' => 'video1.mp4', 'length' => '10:00'],
                    ['id' => 'v2', 'name' => 'Video 2', 'image' => 'image2.jpg', 'video' => 'video2.mp4', 'length' => '15:00'],
                ],
            ]);

        $this->app->instance(WellWoProgramService::class, $mockProgramService);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/wellbeing/wellwo/programs/{$programId}/videos");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'image', 'video', 'length'],
                ],
            ]);
    }
}
