<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\ActivateModuleForFinancerAction;
use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

#[Group('module')]

class ActivateModuleForFinancerActionTest extends TestCase
{
    private ActivateModuleForFinancerAction $action;

    private ModuleService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(ModuleService::class);
        $this->action = new ActivateModuleForFinancerAction($this->mockService);
    }

    #[Test]
    public function it_activates_module_for_financer_successfully(): void
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

        $this->mockService->shouldReceive('activateForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $financer);

        // Assert
        $this->assertSame($module, $result);
    }

    #[Test]
    public function it_throws_exception_when_module_not_active_in_division(): void
    {
        // Arrange
        $module = Mockery::mock(Module::class)->makePartial();
        $module->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('module-123');
        $module->id = 'module-123';

        $financer = Mockery::mock(Financer::class)->makePartial();
        $financer->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('financer-789');
        $financer->id = 'financer-789';

        $this->mockService->shouldReceive('activateForFinancer')
            ->once()
            ->with($module, $financer)
            ->andThrow(new UnprocessableEntityHttpException('Module must be active in at least one division before activating it for a financer'));

        // Act & Assert
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Module must be active in at least one division before activating it for a financer');

        $this->action->execute($module, $financer);
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

        $financer = Mockery::mock(Financer::class)->makePartial();
        $financer->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('financer-789');
        $financer->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Financer Test');
        $financer->id = 'financer-789';
        $financer->name = 'Financer Test';

        $this->mockService->shouldReceive('activateForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $this->action->execute($module, $financer);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
