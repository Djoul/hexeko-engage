<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\WorkMode;

use App\Actions\WorkMode\CreateWorkModeAction;
use App\Models\Financer;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('workmode')]
class CreateWorkModeActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateWorkModeAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateWorkModeAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_work_mode_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Remote',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_work_mode_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Office',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_work_mode_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Flexible',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('work_modes', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_work_mode_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'Part-time',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_work_mode_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Contract',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'Work & Life Balance (Special)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($data['name'], $result->name);
    }
}
