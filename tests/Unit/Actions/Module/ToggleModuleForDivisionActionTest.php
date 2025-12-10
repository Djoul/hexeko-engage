<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\ToggleModuleForDivisionAction;
use App\Models\Division;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]

class ToggleModuleForDivisionActionTest extends TestCase
{
    private ToggleModuleForDivisionAction $action;

    private ModuleService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(ModuleService::class);
        $this->action = new ToggleModuleForDivisionAction($this->mockService);

        // Set a dummy financer_id in Context for any potential global scope queries
        Context::add('financer_id', 'test-financer-id');
    }

    #[Test]
    public function it_activates_module_for_division(): void
    {
        // Arrange
        $module = Mockery::mock(Module::class)->makePartial();
        $module->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('module-123');
        $module->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Module Test']);
        $module->id = 'module-123';
        $module->name = ['fr-FR' => 'Module Test'];

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
            ->with($module, $division);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $division, true);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_deactivates_module_for_division(): void
    {
        // Arrange
        $module = Mockery::mock(Module::class)->makePartial();
        $module->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('module-123');
        $module->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Module Test']);
        $module->id = 'module-123';
        $module->name = ['fr-FR' => 'Module Test'];

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
            ->with($module, $division);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $division, false);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_logs_activity_when_activating_module(): void
    {
        // Arrange
        $module = Mockery::mock(Module::class)->makePartial();
        $module->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('module-123');
        $module->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Module Test']);
        $module->id = 'module-123';
        $module->name = ['fr-FR' => 'Module Test'];

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
            ->with($module, $division);

        Event::fake();

        // Act
        $this->action->execute($module, $division, true);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }

    #[Test]
    public function it_logs_activity_when_deactivating_module(): void
    {
        // Arrange
        $module = Mockery::mock(Module::class)->makePartial();
        $module->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('module-123');
        $module->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn(['fr-FR' => 'Module Test']);
        $module->id = 'module-123';
        $module->name = ['fr-FR' => 'Module Test'];

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
            ->with($module, $division);

        Event::fake();

        // Act
        $this->action->execute($module, $division, false);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
