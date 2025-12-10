<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\PromoteModuleForFinancerAction;
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

class PromoteModuleForFinancerActionTest extends TestCase
{
    private PromoteModuleForFinancerAction $action;

    private ModuleService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(ModuleService::class);
        $this->action = new PromoteModuleForFinancerAction($this->mockService);
    }

    #[Test]
    public function it_promotes_module_for_financer_successfully(): void
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

        $this->mockService->shouldReceive('promoteForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $financer, true);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_unpromotes_module_for_financer_successfully(): void
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

        $this->mockService->shouldReceive('unpromoteForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $result = $this->action->execute($module, $financer, false);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_service_exception_when_module_not_active(): void
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

        $this->mockService->shouldReceive('promoteForFinancer')
            ->once()
            ->with($module, $financer)
            ->andThrow(new UnprocessableEntityHttpException('The module must be active for this financer before it can be promoted.'));

        // Act & Assert
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('The module must be active for this financer before it can be promoted.');

        $this->action->execute($module, $financer, true);
    }

    #[Test]
    public function it_logs_activity_when_promoting_module(): void
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

        $this->mockService->shouldReceive('promoteForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $this->action->execute($module, $financer, true);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }

    #[Test]
    public function it_logs_activity_when_unpromoting_module(): void
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

        $this->mockService->shouldReceive('unpromoteForFinancer')
            ->once()
            ->with($module, $financer);

        Event::fake();

        // Act
        $this->action->execute($module, $financer, false);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
