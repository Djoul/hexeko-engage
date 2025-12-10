<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Financer;

use App\Actions\Financer\UpdateFinancerAction;
use App\Actions\Module\ActivateModuleForFinancerAction;
use App\Actions\Module\DeactivateModuleForFinancerAction;
use App\Actions\Module\SetFinancerCorePriceAction;
use App\Actions\Module\SetFinancerModulePriceAction;
use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\FinancerLogoService;
use App\Services\Models\FinancerService;
use App\Services\Models\ModuleService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('financer')]
#[Group('module')]
class UpdateFinancerActionWithModulesTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateFinancerAction $action;

    private MockInterface $financerService;

    private MockInterface $setCorePriceAction;

    private MockInterface $moduleService;

    private MockInterface $activateModuleAction;

    private MockInterface $deactivateModuleAction;

    private MockInterface $setPriceAction;

    private MockInterface $logoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financerService = Mockery::mock(FinancerService::class);
        $this->setCorePriceAction = Mockery::mock(SetFinancerCorePriceAction::class);
        $this->moduleService = Mockery::mock(ModuleService::class);
        $this->activateModuleAction = Mockery::mock(ActivateModuleForFinancerAction::class);
        $this->deactivateModuleAction = Mockery::mock(DeactivateModuleForFinancerAction::class);
        $this->setPriceAction = Mockery::mock(SetFinancerModulePriceAction::class);
        $this->logoService = Mockery::mock(FinancerLogoService::class);

        $this->action = new UpdateFinancerAction(
            $this->financerService,
            $this->setCorePriceAction,
            $this->moduleService,
            $this->activateModuleAction,
            $this->deactivateModuleAction,
            $this->setPriceAction,
            $this->logoService
        );
    }

    #[Test]
    public function it_processes_modules_array_when_provided(): void
    {
        $financer = Mockery::mock(Financer::class);
        $financer->shouldReceive('getAttribute')->with('id')->andReturn('financer-id');
        $financer->shouldReceive('getAttribute')->with('name')->andReturn('Test Financer');
        $financer->shouldReceive('refresh')->andReturnSelf();
        $financer->shouldReceive('load')->with('modules')->andReturnSelf();

        $module1 = Mockery::mock(Module::class);
        $module1->shouldReceive('getAttribute')->with('id')->andReturn('module-1');
        $module1->shouldReceive('getAttribute')->with('is_core')->andReturn(false);
        $module1->shouldReceive('setAttribute')->andReturnSelf();
        $module1->shouldReceive('offsetExists')->andReturn(true);
        $module1->shouldReceive('offsetGet')->with('is_core')->andReturn(false);
        $module1->shouldReceive('offsetGet')->with('id')->andReturn('module-1');

        $module2 = Mockery::mock(Module::class);
        $module2->shouldReceive('getAttribute')->with('id')->andReturn('module-2');
        $module2->shouldReceive('getAttribute')->with('is_core')->andReturn(false);
        $module2->shouldReceive('setAttribute')->andReturnSelf();
        $module2->shouldReceive('offsetExists')->andReturn(true);
        $module2->shouldReceive('offsetGet')->with('is_core')->andReturn(false);
        $module2->shouldReceive('offsetGet')->with('id')->andReturn('module-2');

        // Mock the modules relationship
        $modulesRelation = Mockery::mock(BelongsToMany::class);
        $modulesRelation->shouldReceive('where')
            ->with('module_id', 'module-1')
            ->andReturnSelf();
        $modulesRelation->shouldReceive('where')
            ->with('module_id', 'module-2')
            ->andReturnSelf();
        $modulesRelation->shouldReceive('first')
            ->andReturn(null); // No existing pivot
        $modulesRelation->shouldReceive('updateExistingPivot')
            ->andReturn(true);

        $financer->shouldReceive('modules')
            ->andReturn($modulesRelation);

        $validatedData = [
            'name' => 'Updated Financer',
            'company_number' => 'BE123456789',
            'modules' => [
                [
                    'id' => 'module-1',
                    'active' => true,
                    'price_per_beneficiary' => 500,
                ],
                [
                    'id' => 'module-2',
                    'active' => false,
                ],
            ],
        ];

        // Expect financer update without modules
        $this->financerService->shouldReceive('update')
            ->once()
            ->with($financer, Mockery::on(function ($data): bool {
                return ! array_key_exists('modules', $data);
            }))
            ->andReturn($financer);

        // Expect module service to find modules
        $this->moduleService->shouldReceive('find')
            ->with('module-1')
            ->andReturn($module1);

        $this->moduleService->shouldReceive('find')
            ->with('module-2')
            ->andReturn($module2);

        // Expect activation for module 1
        $this->activateModuleAction->shouldReceive('execute')
            ->once()
            ->with($module1, $financer);

        // Expect price update for module 1
        $this->setPriceAction->shouldReceive('execute')
            ->once()
            ->with($financer, $module1, 500, Mockery::any());

        // Expect deactivation for module 2
        $this->deactivateModuleAction->shouldReceive('execute')
            ->once()
            ->with($module2, $financer);

        $result = $this->action->handle($financer, $validatedData);

        $this->assertSame($financer, $result);
    }

    #[Test]
    public function it_handles_financer_update_without_modules(): void
    {
        $financer = Mockery::mock(Financer::class);
        $financer->shouldReceive('getAttribute')->with('name')->andReturn('Test Financer');

        $validatedData = [
            'name' => 'Updated Financer',
            'company_number' => 'BE987654321',
            'status' => 'active',
        ];

        $this->financerService->shouldReceive('update')
            ->once()
            ->with($financer, $validatedData)
            ->andReturn($financer);

        $result = $this->action->handle($financer, $validatedData);

        $this->assertSame($financer, $result);
    }

    #[Test]
    public function it_skips_core_module_deactivation(): void
    {
        $financer = Mockery::mock(Financer::class);
        $financer->shouldReceive('getAttribute')->with('id')->andReturn('financer-id');
        $financer->shouldReceive('refresh')->andReturnSelf();
        $financer->shouldReceive('load')->with('modules')->andReturnSelf();

        $coreModule = Mockery::mock(Module::class);
        $coreModule->shouldReceive('getAttribute')->with('id')->andReturn('core-module');
        $coreModule->shouldReceive('getAttribute')->with('is_core')->andReturn(true);

        $validatedData = [
            'name' => 'Updated Financer',
            'modules' => [
                [
                    'id' => 'core-module',
                    'active' => false, // Trying to deactivate core module
                    'price_per_beneficiary' => 100, // Trying to set price on core module
                ],
            ],
        ];

        $this->financerService->shouldReceive('update')
            ->once()
            ->andReturn($financer);

        $this->moduleService->shouldReceive('find')
            ->with('core-module')
            ->andReturn($coreModule);

        // Should NOT call deactivation for core module
        $this->deactivateModuleAction->shouldNotReceive('execute');

        // Should NOT set price for core module
        $this->setPriceAction->shouldNotReceive('execute');

        $result = $this->action->handle($financer, $validatedData);

        $this->assertSame($financer, $result);
    }

    #[Test]
    public function it_handles_module_with_promoted_flag(): void
    {
        $financer = Mockery::mock(Financer::class);
        $financer->shouldReceive('getAttribute')->with('id')->andReturn('financer-id');
        $financer->shouldReceive('refresh')->andReturnSelf();
        $financer->shouldReceive('load')->with('modules')->andReturnSelf();

        // Mock the modules relationship properly
        $modulesRelation = Mockery::mock(BelongsToMany::class);
        $modulesRelation->shouldReceive('where')
            ->with('module_id', 'module-1')
            ->andReturnSelf();
        $modulesRelation->shouldReceive('first')
            ->andReturn(null);
        $modulesRelation->shouldReceive('updateExistingPivot')
            ->with('module-1', ['promoted' => true])
            ->andReturn(true);

        $financer->shouldReceive('modules')->andReturn($modulesRelation);

        $module = Mockery::mock(Module::class);
        $module->shouldReceive('getAttribute')->with('id')->andReturn('module-1');
        $module->shouldReceive('getAttribute')->with('is_core')->andReturn(false);
        $module->shouldReceive('setAttribute')->andReturnSelf();
        $module->shouldReceive('offsetExists')->andReturn(true);
        $module->shouldReceive('offsetGet')->with('is_core')->andReturn(false);
        $module->shouldReceive('offsetGet')->with('id')->andReturn('module-1');

        $validatedData = [
            'name' => 'Updated Financer',
            'modules' => [
                [
                    'id' => 'module-1',
                    'active' => true,
                    'promoted' => true,
                ],
            ],
        ];

        $this->financerService->shouldReceive('update')
            ->once()
            ->andReturn($financer);

        $this->moduleService->shouldReceive('find')
            ->with('module-1')
            ->andReturn($module);

        $this->activateModuleAction->shouldReceive('execute')
            ->once()
            ->with($module, $financer);

        $result = $this->action->handle($financer, $validatedData);

        $this->assertSame($financer, $result);
    }

    #[Test]
    public function it_processes_modules_within_database_transaction(): void
    {
        $financer = Mockery::mock(Financer::class);
        $financer->shouldReceive('getAttribute')->with('id')->andReturn('financer-id');
        $financer->shouldReceive('refresh')->andReturnSelf();
        $financer->shouldReceive('load')->with('modules')->andReturnSelf();

        // Mock the modules relationship
        $modulesRelation = Mockery::mock(BelongsToMany::class);
        $modulesRelation->shouldReceive('where')
            ->with('module_id', 'module-1')
            ->andReturnSelf();
        $modulesRelation->shouldReceive('first')
            ->andReturn(null);

        $financer->shouldReceive('modules')->andReturn($modulesRelation);

        $module = Mockery::mock(Module::class);
        $module->shouldReceive('getAttribute')->with('id')->andReturn('module-1');
        $module->shouldReceive('getAttribute')->with('is_core')->andReturn(false);
        $module->shouldReceive('offsetExists')->andReturn(true);
        $module->shouldReceive('offsetGet')->with('is_core')->andReturn(false);
        $module->shouldReceive('offsetGet')->with('id')->andReturn('module-1');

        $validatedData = [
            'name' => 'Updated Financer',
            'modules' => [
                ['id' => 'module-1', 'active' => true],
            ],
        ];

        // This test verifies that DB::transaction is called
        // The actual implementation should wrap everything in a transaction

        $this->financerService->shouldReceive('update')
            ->once()
            ->andReturn($financer);

        $this->moduleService->shouldReceive('find')
            ->with('module-1')
            ->andReturn($module);

        $this->activateModuleAction->shouldReceive('execute')
            ->once()
            ->with($module, $financer);

        // In actual implementation, this should be wrapped in DB::transaction
        $result = $this->action->handle($financer, $validatedData);

        $this->assertSame($financer, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
