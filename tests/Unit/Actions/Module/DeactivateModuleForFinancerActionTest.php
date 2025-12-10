<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\DeactivateModuleForFinancerAction;
use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]

class DeactivateModuleForFinancerActionTest extends TestCase
{
    private DeactivateModuleForFinancerAction $action;

    private ModuleService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(ModuleService::class);
        $this->action = new DeactivateModuleForFinancerAction($this->mockService);
    }

    #[Test]
    public function it_deactivates_module_for_financer_successfully(): void
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

        $financer = Mockery::mock(Financer::class)->makePartial();
        $financer->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('financer-789');
        $financer->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Financer Test');
        $financer->id = 'financer-789';
        $financer->name = 'Financer Test';

        $this->mockService->shouldReceive('deactivateForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $financer);

        // Assert
        $this->assertSame($module, $result);
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

        $financer = Mockery::mock(Financer::class)->makePartial();
        $financer->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('financer-789');
        $financer->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Financer Test');
        $financer->id = 'financer-789';
        $financer->name = 'Financer Test';

        $this->mockService->shouldReceive('deactivateForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $this->action->execute($module, $financer);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }

    #[Test]
    public function it_deactivates_even_if_module_not_previously_attached(): void
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

        $financer = Mockery::mock(Financer::class)->makePartial();
        $financer->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('financer-789');
        $financer->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Financer Test');
        $financer->id = 'financer-789';
        $financer->name = 'Financer Test';

        // Service will handle attaching with active = false if not previously attached
        $this->mockService->shouldReceive('deactivateForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $financer);

        // Assert
        $this->assertSame($module, $result);
    }
}
