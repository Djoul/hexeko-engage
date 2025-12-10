<?php

namespace Tests\Unit\Http\Middleware;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('middleware')]
#[Group('permission')]
#[Group('auth')]
class CheckPermissionAttributeTest extends ProtectedRouteTestCase
{
    protected $user;

    // Enable permission checks for this test suite
    protected bool $checkPermissions = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable CognitoAuthMiddleware to avoid having to manage Cognito tokens
        $this->withoutMiddleware(CognitoAuthMiddleware::class);

        $team = ModelFactory::createTeam();
        $role = ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $team->id]);
        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::READ_USER]);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create(['team_id' => $team->id]);

        // Initialize contextual access arrays used by filters
        Context::add('accessible_financers', []);
        Context::add('accessible_divisions', []);
    }

    #[Test]
    public function authenticated_user_with_permission_can_access_protected_route(): void
    {
        $this->withoutExceptionHandling(); // Disable exception handling to see the actual response
        $this->user->assignRole(RoleDefaults::HEXEKO_SUPER_ADMIN); // Assign role with permission to user

        $response = $this->actingAs($this->user)
            ->getJson(route('users.index')); // Route protected by RequiresPermission attribute

        $response->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_without_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('users.index')); // Route protected by RequiresPermission attribute

        $response->assertStatus(403);
    }
}
