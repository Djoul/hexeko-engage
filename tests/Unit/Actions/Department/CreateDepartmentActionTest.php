<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Department;

use App\Actions\Department\CreateDepartmentAction;
use App\Models\Department;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('department')]
class CreateDepartmentActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateDepartmentAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateDepartmentAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_department_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Department',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertTrue($result->exists);

        // Test name
        $this->assertEquals($data['name'], $result->name);

        // Test financer_id
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_department_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Minimal Department',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_department_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'HR Department',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('departments', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        // Verify name is stored
        $department = Department::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($department);
        $this->assertEquals($data['name'], $department->name);
    }

    #[Test]
    public function it_returns_refreshed_department_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'IT Department',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertNotNull($result->id);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_department_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Finance Department',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
        $this->assertNotEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'R&D Department (Research & Development)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertEquals($data['name'], $result->name);
    }
}
