<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserShowController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

/**
 * Security tests for ShowUserAction to prevent IDOR vulnerabilities
 *
 * This test suite verifies that users can only access user data
 * from their own financer(s), preventing Insecure Direct Object Reference attacks.
 */
#[Group('user')]
class ShowUserSecurityTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $accessibleFinancer;

    private Financer $inaccessibleFinancer;

    private User $authUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup financers - two separate organizations
        $this->accessibleFinancer = ModelFactory::createFinancer();
        $this->inaccessibleFinancer = ModelFactory::createFinancer();

        // Set financer context for the authenticated user
        Context::add('accessible_financers', [$this->accessibleFinancer->id]);

        // Create authenticated user belonging to accessibleFinancer
        $this->authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->authUser->financers()->attach($this->accessibleFinancer->id, ['active' => true]);
    }

    #[Test]
    public function it_prevents_idor_attack_by_blocking_access_to_user_from_different_financer(): void
    {
        // Arrange - Create a user belonging to a DIFFERENT financer
        $targetUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act - Attempt to access user from inaccessible financer
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$targetUser->id}");

        // Assert - Should return 403 Forbidden (not 200)
        $response->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to view this user',
            ]);
    }

    #[Test]
    public function it_allows_access_to_user_from_same_financer(): void
    {
        // Arrange - Create a user belonging to the SAME financer
        $targetUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$targetUser->id}");

        // Assert - Should return 200 with user data
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $targetUser->id,
                ],
            ]);
    }

    #[Test]
    public function it_allows_god_role_to_access_any_user_regardless_of_financer(): void
    {
        // Arrange - Create GOD user
        $godUser = $this->createAuthUser(RoleDefaults::GOD);

        // Create user in inaccessible financer
        $targetUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act - GOD should access any user
        $response = $this->actingAs($godUser)
            ->getJson("/api/v1/users/{$targetUser->id}");

        // Assert - GOD bypasses financer isolation
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $targetUser->id,
                ],
            ]);
    }

    #[Test]
    public function it_allows_access_to_user_belonging_to_multiple_financers_when_one_is_accessible(): void
    {
        // Arrange - User belongs to BOTH financers
        $targetUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => true],
                ['financer' => $this->inaccessibleFinancer, 'active' => true],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$targetUser->id}");

        // Assert - Should succeed because user shares at least one financer
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $targetUser->id,
                ],
            ]);
    }

    #[Test]
    public function it_blocks_access_when_user_financer_attachment_is_inactive(): void
    {
        // Arrange - User attached to financer but with active=false
        $targetUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->accessibleFinancer, 'active' => false],
            ],
        ]);

        // Act
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$targetUser->id}");

        // Assert - Should block access because attachment is inactive
        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_404_for_non_existent_user(): void
    {
        // Act - Attempt to access non-existent user
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$nonExistentId}");

        // Assert - Should return 404, not 403
        $response->assertNotFound();
    }

    #[Test]
    public function it_allows_user_to_access_their_own_profile(): void
    {
        // Arrange - Auth user attempts to access themselves
        $this->authUser->financers()->attach($this->accessibleFinancer->id, ['active' => true]);
        $this->authUser->refresh();

        // Act
        $response = $this->actingAs($this->authUser)
            ->getJson("/api/v1/users/{$this->authUser->id}");

        // Assert - Users should always be able to view their own profile
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->authUser->id,
                ],
            ]);
    }
}
