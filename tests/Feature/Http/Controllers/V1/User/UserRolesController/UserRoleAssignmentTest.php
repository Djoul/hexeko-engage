<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserRolesController;

use App\Enums\IDP\RoleDefaults;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserRoleAssignmentTest extends ProtectedRouteTestCase
{
    #[Test]
    public function a_user_can_assign_role_via_proper_endpoint(): void
    {
        $this->withoutExceptionHandling();

        $auth = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Ensure role exists in same team as auth
        $this->createRoleAndPermissions(RoleDefaults::FINANCER_SUPER_ADMIN, $auth->team);

        // Target user shares same financer as auth
        $user = ModelFactory::createUser([
            'team_id' => $auth->team_id, // ensure same team for role resolution
            'financers' => [
                ['financer' => $auth->financers->first()],
            ],
        ]);

        $response = $this->actingAs($auth)
            ->postJson(
                route('user.assign_role', [
                    'user' => $user->id,
                    'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
                ])
            );

        $response->assertOk();

        $this->assertTrue($user->fresh()->hasRole(RoleDefaults::FINANCER_SUPER_ADMIN));
    }

    #[Test]
    public function a_user_cannot_assign_a_higher_role_than_their_own(): void
    {
        $auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);

        // Ensure higher role exists in same team
        $this->createRoleAndPermissions(RoleDefaults::DIVISION_ADMIN, $auth->team);

        // Target user shares same financer as auth
        $user = ModelFactory::createUser([
            'team_id' => $auth->team_id, // ensure same team for role resolution
            'financers' => [
                ['financer' => $auth->financers->first()],
            ],
        ]);

        $this->actingAs($auth)
            ->postJson(
                route('user.assign_role', [
                    'user' => $user->id,
                    'role' => RoleDefaults::DIVISION_ADMIN,
                ])
            )
            ->assertStatus(403);
    }

    #[Test]
    public function a_user_can_remove_role(): void
    {
        $auth = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Ensure roles exist in same team
        $this->createRoleAndPermissions(RoleDefaults::FINANCER_SUPER_ADMIN, $auth->team);
        $this->createRoleAndPermissions(RoleDefaults::BENEFICIARY, $auth->team);

        // Target user shares same financer as auth
        $user = ModelFactory::createUser([
            'team_id' => $auth->team_id, // ensure same team for role resolution
            'financers' => [
                ['financer' => $auth->financers->first()],
            ],
        ]);

        setPermissionsTeamId($user->team_id);
        $user->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $user->assignRole(RoleDefaults::BENEFICIARY);

        $this->actingAs($auth)
            ->deleteJson(
                route('user.remove_role', [
                    'user' => $user->id,
                    'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
                ])
            )
            ->assertOk();

        $this->assertFalse($user->fresh()->hasRole(RoleDefaults::FINANCER_SUPER_ADMIN));
        $this->assertTrue($user->fresh()->hasRole(RoleDefaults::BENEFICIARY));
    }

    protected function setUp(): void
    {
        // Focus on feature behavior; bypass permission middleware
        $this->checkPermissions = false;
        parent::setUp();
    }
}
