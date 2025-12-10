<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\ToggleUserActivationController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class ToggleUserActivationTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $accessibleFinancer;

    private Financer $inaccessibleFinancer;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup financers
        $division = ModelFactory::createDivision();
        $this->accessibleFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $this->inaccessibleFinancer = ModelFactory::createFinancer();

        // Setup auth user with UPDATE_USER permission
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, returnDetails: true);

        // Attach the accessible financer to the auth user
        $this->auth->financers()->attach($this->accessibleFinancer->id, [
            'active' => true,
            'role' => RoleDefaults::FINANCER_ADMIN,
        ]);
        $this->auth->refresh();

        // Hydrate authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->accessibleFinancer->id],
            [$division->id],
            [],
            $this->accessibleFinancer->id
        );

        // Set context with accessible financer only
        Context::add('accessible_financers', [$this->accessibleFinancer->id]);
        Context::add('accessible_divisions', [$division->id]);

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $this->accessibleFinancer->id);
    }

    #[Test]
    public function it_deactivates_active_user_for_accessible_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/user/{$user->id}/toggle-activation/{$this->accessibleFinancer->id}");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user_id',
                'financer_id',
                'active',
            ])
            ->assertJson([
                'active' => false,
                'user_id' => $user->id,
                'financer_id' => $this->accessibleFinancer->id,
            ]);

        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $this->accessibleFinancer->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function it_activates_inactive_user_for_accessible_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => false],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/user/{$user->id}/toggle-activation/{$this->accessibleFinancer->id}");

        // Assert
        $response->assertOk()
            ->assertJson(['active' => true]);

        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $this->accessibleFinancer->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_returns_403_for_inaccessible_financer(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/user/{$user->id}/toggle-activation/{$this->inaccessibleFinancer->id}");

        // Assert
        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have access to this financer',
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_user(): void
    {
        // Act - Using a valid UUID format that doesn't exist in DB
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->auth)
            ->putJson("/api/v1/user/{$nonExistentId}/toggle-activation/{$this->accessibleFinancer->id}");

        // Assert
        $response->assertNotFound();
    }
}
