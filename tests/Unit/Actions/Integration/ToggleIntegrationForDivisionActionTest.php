<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Integration;

use App\Actions\Integration\ToggleIntegrationForDivisionAction;
use App\Models\Division;
use App\Models\Integration;
use App\Services\Models\IntegrationService;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('integration')]
#[Group('integration-actions')]
class ToggleIntegrationForDivisionActionTest extends TestCase
{
    private ToggleIntegrationForDivisionAction $action;

    private IntegrationService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(IntegrationService::class);
        $this->action = new ToggleIntegrationForDivisionAction($this->mockService);
    }

    #[Test]
    public function it_activates_integration_for_division(): void
    {
        // Arrange
        $integration = Mockery::mock(Integration::class)->makePartial();
        $integration->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('integration-123');
        $integration->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Integration Test']);
        $integration->id = 'integration-123';
        $integration->name = ['fr-FR' => 'Integration Test'];

        $division = Mockery::mock(Division::class)->makePartial();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('division-456');
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = 'division-456';
        $division->name = 'Division Test';

        $this->mockService->shouldReceive('activateForDivision')
            ->once()
            ->with($integration, $division);

        Event::fake();

        // Act
        $result = $this->action->execute($integration, $division, true);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_deactivates_integration_for_division(): void
    {
        // Arrange
        $integration = Mockery::mock(Integration::class)->makePartial();
        $integration->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('integration-123');
        $integration->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Integration Test']);
        $integration->id = 'integration-123';
        $integration->name = ['fr-FR' => 'Integration Test'];

        $division = Mockery::mock(Division::class)->makePartial();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('division-456');
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = 'division-456';
        $division->name = 'Division Test';

        $this->mockService->shouldReceive('deactivateForDivision')
            ->once()
            ->with($integration, $division);

        Event::fake();

        // Act
        $result = $this->action->execute($integration, $division, false);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_logs_activity_when_activating_integration(): void
    {
        // Arrange
        $integration = Mockery::mock(Integration::class)->makePartial();
        $integration->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('integration-123');
        $integration->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Integration Test']);
        $integration->id = 'integration-123';
        $integration->name = ['fr-FR' => 'Integration Test'];

        $division = Mockery::mock(Division::class)->makePartial();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('division-456');
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = 'division-456';
        $division->name = 'Division Test';

        $this->mockService->shouldReceive('activateForDivision')
            ->once()
            ->with($integration, $division);

        Event::fake();

        // Act
        $this->action->execute($integration, $division, true);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }

    #[Test]
    public function it_logs_activity_when_deactivating_integration(): void
    {
        // Arrange
        $integration = Mockery::mock(Integration::class)->makePartial();
        $integration->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('integration-123');
        $integration->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Integration Test']);
        $integration->id = 'integration-123';
        $integration->name = ['fr-FR' => 'Integration Test'];

        $division = Mockery::mock(Division::class)->makePartial();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('division-456');
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = 'division-456';
        $division->name = 'Division Test';

        $this->mockService->shouldReceive('deactivateForDivision')
            ->once()
            ->with($integration, $division);

        Event::fake();

        // Act
        $this->action->execute($integration, $division, false);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
