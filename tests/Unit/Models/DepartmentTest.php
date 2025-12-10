<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('department')]
class DepartmentTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $department = new Department;

        $this->assertTrue($department->getIncrementing() === false);
        $this->assertEquals('string', $department->getKeyType());
    }

    #[Test]
    public function it_can_create_a_department(): void
    {
        // Arrange

        // Act
        $department = Department::factory()->create([
            'name' => 'Test Department',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(Department::class, $department);
        $this->assertEquals('Test Department', $department->name);
        $this->assertEquals($this->financer->id, $department->financer_id);
    }

    #[Test]
    public function it_can_update_a_department(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Original Department',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Updated Department',
        ];

        // Act
        $department->update($updatedData);

        // Assert
        $this->assertEquals('Updated Department', $department->name);
    }

    #[Test]
    public function it_can_soft_delete_a_department(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Department to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $department->delete();

        // Assert
        $this->assertSoftDeleted('departments', ['id' => $department->id]);
        $this->assertTrue($department->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_department(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Department to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $department->delete();

        // Act
        $department->restore();

        // Assert
        $this->assertFalse($department->trashed());
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $department = Department::create([
            'name' => 'Department with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $department->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_has_many_users(): void
    {
        // Arrange
        $department = Department::factory()->create([
            'name' => 'Test Department',
            'financer_id' => $this->financer->id,
        ]);

        User::factory(5)->create()->each(function ($user) use ($department): void {
            $department->users()->attach($user->id);
        });

        // Act
        $retrievedUsers = $department->users;

        // Assert
        $this->assertCount(5, $retrievedUsers);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $department = Department::factory()->create([
            'name' => 'Department with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $department->created_by);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $department = Department::factory()->create([
            'name' => 'Department without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($department->created_by);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $department = Department::factory()->create([
            'name' => 'Department to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $department->update([
            'name' => 'Updated Department Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $department->created_by);
        $this->assertEquals($updater->id, $department->updated_by);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();

        Auth::login($creator);
        $department = Department::factory()->create([
            'name' => 'Department with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $department->creator);
        $this->assertEquals($creator->id, $department->creator->id);
        $this->assertEquals($creator->name, $department->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $department = Department::factory()->create([
            'name' => 'Department with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $department->update([
            'name' => 'Updated Department',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $department->updater);
        $this->assertEquals($updater->id, $department->updater->id);
        $this->assertEquals($updater->name, $department->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $department = Department::factory()->create([
            'name' => 'Department to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($department->wasCreatedBy($creator));
        $this->assertFalse($department->wasCreatedBy($otherUser));
        $this->assertFalse($department->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $department = Department::factory()->create([
            'name' => 'Department to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $department->update([
            'name' => 'Updated Department',
        ]);

        // Assert
        $this->assertTrue($department->wasUpdatedBy($updater));
        $this->assertFalse($department->wasUpdatedBy($creator));
        $this->assertFalse($department->wasUpdatedBy($otherUser));
        $this->assertFalse($department->wasUpdatedBy(null));
    }
}
