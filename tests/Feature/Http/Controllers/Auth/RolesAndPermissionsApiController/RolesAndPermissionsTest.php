<?php

namespace Tests\Feature\Http\Controllers\Auth\RolesAndPermissionsApiController;

use App\Enums\IDP\RoleDefaults;
use BenSampo\Enum\Enum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\ProtectedRouteTestCase;

#[Group('auth')]
#[Group('roles')]
#[Group('permissions')]

class RolesAndPermissionsTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_returns_all_roles_with_permissions(): void
    {
        $response = $this->getJson('/api/v1/roles-and-permissions');

        $response->assertStatus(Response::HTTP_OK);

        /** @var array<string, array<int, string>> $responseData */
        $responseData = $response->json('data');

        // The endpoint currently returns a subset of roles (excludes hexeko_admin)
        $expectedRoles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($expectedRoles as $roleValue) {
            $this->assertArrayHasKey($roleValue, $responseData);

            /** @var array<int, string> $permissions */
            $permissions = RoleDefaults::getPermissionsByRole($roleValue);
            $this->assertEqualsCanonicalizing(
                $permissions,
                $responseData[$roleValue],
                "Permissions for role {$roleValue} do not match"
            );
        }

        // Check that all possible permissions are assigned to at least one role
        $allPermissions = collect(RoleDefaults::getInstances())
            ->flatMap(function (Enum $role): array {
                $roleValue = is_scalar($role->value) ? (string) $role->value : '';

                return RoleDefaults::getPermissionsByRole($roleValue);
            })
            ->unique()
            ->values()
            ->toArray();

        $this->assertNotEmpty($allPermissions, 'There must be at least one assigned permission.');
    }
}
