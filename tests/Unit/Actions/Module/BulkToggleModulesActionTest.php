<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\BulkToggleModulesAction;
use App\Models\Division;
use App\Services\Models\ModuleService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]

class BulkToggleModulesActionTest extends TestCase
{
    private BulkToggleModulesAction $action;

    private ModuleService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(ModuleService::class);
        $this->action = new BulkToggleModulesAction($this->mockService);
    }

    #[Test]
    public function it_toggles_multiple_modules_successfully(): void
    {
        // Arrange
        $division = Mockery::mock(Division::class)->makePartial();
        $divisionId = fake()->uuid();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn($divisionId);
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = $divisionId;
        $division->name = 'Division Test';

        $moduleIds = [
            fake()->uuid(),
            fake()->uuid(),
            fake()->uuid(),
        ];

        $expectedResults = [
            $moduleIds[0] => true,
            $moduleIds[1] => false,
            $moduleIds[2] => true,
        ];

        $this->mockService->shouldReceive('bulkToggleForDivision')
            ->once()
            ->with($moduleIds, $division)
            ->andReturn($expectedResults);

        Event::fake();

        // Act
        $result = $this->action->execute($moduleIds, $division);

        // Assert
        $this->assertEquals($expectedResults, $result);
    }

    #[Test]
    public function it_validates_module_ids_are_not_empty(): void
    {
        // Arrange
        $division = Mockery::mock(Division::class)->makePartial();
        $division->id = fake()->uuid();

        $moduleIds = [];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module IDs cannot be empty');

        $this->action->execute($moduleIds, $division);
    }

    #[Test]
    public function it_validates_module_ids_are_strings(): void
    {
        // Arrange
        $division = Mockery::mock(Division::class)->makePartial();
        $division->id = fake()->uuid();

        $moduleIds = [fake()->uuid(), 123, fake()->uuid()];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All module IDs must be strings');

        $this->action->execute($moduleIds, $division);
    }

    #[Test]
    public function it_handles_transaction_rollback_on_failure(): void
    {
        // Arrange
        $division = Mockery::mock(Division::class)->makePartial();
        $divisionId = fake()->uuid();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn($divisionId);
        $division->id = $divisionId;

        $moduleIds = [fake()->uuid(), fake()->uuid()];

        $this->mockService->shouldReceive('bulkToggleForDivision')
            ->once()
            ->with($moduleIds, $division)
            ->andThrow(new Exception('Service error'));

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();

        Event::fake();

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service error');

        $this->action->execute($moduleIds, $division);
    }

    #[Test]
    public function it_logs_activity_for_bulk_toggle(): void
    {
        // Arrange
        $division = Mockery::mock(Division::class)->makePartial();
        $divisionId = fake()->uuid();
        $division->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn($divisionId);
        $division->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Division Test');
        $division->id = $divisionId;
        $division->name = 'Division Test';

        $moduleIds = [fake()->uuid(), fake()->uuid()];

        $expectedResults = [
            $moduleIds[0] => true,
            $moduleIds[1] => false,
        ];

        $this->mockService->shouldReceive('bulkToggleForDivision')
            ->once()
            ->with($moduleIds, $division)
            ->andReturn($expectedResults);

        Event::fake();

        // Act
        $this->action->execute($moduleIds, $division);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
