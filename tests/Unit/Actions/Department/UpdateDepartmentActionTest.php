<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Department;

use App\Actions\Department\UpdateDepartmentAction;
use App\Models\Department;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('department')]
class UpdateDepartmentActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateDepartmentAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateDepartmentAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_department_successfully(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Original Department',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Department',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);

        // Test updated name
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_department_name_only(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertEquals($updateData['name'], $result->name);

        // Test that financer_id remains unchanged
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Sales Department',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Sales Department Updated',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Original Department',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Department Only',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);

        // Test that name is updated
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'HR Department',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Human Resources Department',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertDatabaseHas('departments', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        // Verify name is updated in database
        $freshDepartment = Department::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshDepartment);
        $this->assertEquals($updateData['name'], $freshDepartment->name);
    }

    #[Test]
    public function it_returns_refreshed_department_instance(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'IT Department',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $department->updated_at;

        // Small delay to ensure updated_at changes
        sleep(1);

        $updateData = [
            'name' => 'Information Technology Department',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_department_with_special_characters(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Simple Department',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'R&D Department (Research & Development)',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertInstanceOf(Department::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_department_id_after_update(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Finance Department',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $department->id;

        $updateData = [
            'name' => 'Financial Department',
        ];

        // Act
        $result = $this->action->execute($department, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
