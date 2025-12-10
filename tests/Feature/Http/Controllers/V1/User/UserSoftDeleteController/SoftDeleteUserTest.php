<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserSoftDeleteController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class SoftDeleteUserTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $accessibleFinancer;

    private Financer $inaccessibleFinancer;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup financers
        $this->accessibleFinancer = ModelFactory::createFinancer();
        $this->inaccessibleFinancer = ModelFactory::createFinancer();

        // Set context
        Context::add('accessible_financers', [$this->accessibleFinancer->id]);

        // Setup auth user with DELETE_USER permission
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
    }

    #[Test]
    public function it_soft_deletes_user_with_single_accessible_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
            ],
        ]);
        $initialTotalCount = User::withTrashed()->count();
        $initialActiveCount = User::count();

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertNoContent();

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);

        // Total count unchanged (soft delete)
        $this->assertEquals($initialTotalCount, User::withTrashed()->count());
        // Active count decreased by 1
        $this->assertEquals($initialActiveCount - 1, User::count());
    }

    #[Test]
    public function it_soft_deletes_user_without_any_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser(); // No financer attachment

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_returns_403_when_user_has_multiple_financers(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cannot delete user attached to multiple financers',
            ]);

        // User NOT deleted
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_returns_403_when_user_belongs_to_inaccessible_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertForbidden()
            ->assertJson([
                'message' => 'User belongs to a financer you do not have access to',
            ]);

        // User NOT deleted
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_returns_404_for_already_deleted_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
            ],
        ]);
        $user->delete(); // Soft delete first

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_non_existent_user(): void
    {
        // Act - Using a valid UUID format that doesn't exist in DB
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$nonExistentId}");

        // Assert
        $response->assertNotFound();
    }

    #[Test]
    public function it_allows_delete_when_user_has_inactive_financer_attachment(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => false],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        // Assert
        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
