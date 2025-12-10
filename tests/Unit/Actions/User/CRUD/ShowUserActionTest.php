<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User\CRUD;

use App\Actions\User\CRUD\ShowUserAction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('actions')]
class ShowUserActionTest extends TestCase
{
    use DatabaseTransactions;

    private ShowUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ShowUserAction;
    }

    #[Test]
    public function it_retrieves_user_with_default_relations(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
        ]);

        // Act
        $result = $this->action->execute($user->id);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('John', $result->first_name);
        $this->assertEquals('Doe', $result->last_name);
        $this->assertEquals('john.doe@test.com', $result->email);

        // Verify default relations are loaded
        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('permissions'));
        $this->assertTrue($result->relationLoaded('financers'));
    }

    #[Test]
    public function it_retrieves_user_with_custom_relations(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'custom@test.com',
        ]);

        // Act
        $result = $this->action->execute($user->id, ['financers', 'media']);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);

        // Verify custom relations are loaded
        $this->assertTrue($result->relationLoaded('financers'));
        $this->assertTrue($result->relationLoaded('media'));

        // Verify default relations are NOT loaded (custom relations provided)
        $this->assertFalse($result->relationLoaded('roles'));
        $this->assertFalse($result->relationLoaded('permissions'));
    }

    #[Test]
    public function it_throws_exception_for_non_existent_user(): void
    {
        // Arrange
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->action->execute($nonExistentId);
    }

    #[Test]
    public function it_retrieves_user_with_financer_relationships(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'email' => 'user@financer.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Act
        $result = $this->action->execute($user->id);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->relationLoaded('financers'));
        $this->assertCount(1, $result->financers);
        $this->assertEquals($financer->id, $result->financers->first()->id);
    }

    #[Test]
    public function it_retrieves_user_with_roles_and_permissions(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'user@roles.com',
        ]);

        // Create and assign role
        $role = ModelFactory::createRole(['name' => 'test-role']);
        $user->assignRole($role);

        // Act
        $result = $this->action->execute($user->id);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('permissions'));
        $this->assertCount(1, $result->roles);
        $this->assertEquals('test-role', $result->roles->first()->name);
    }

    #[Test]
    public function it_handles_empty_relations_array(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'empty@relations.com',
        ]);

        // Act - empty array should use defaults
        $result = $this->action->execute($user->id, []);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Verify default relations are loaded (empty array = use defaults)
        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('permissions'));
        $this->assertTrue($result->relationLoaded('financers'));
    }
}
