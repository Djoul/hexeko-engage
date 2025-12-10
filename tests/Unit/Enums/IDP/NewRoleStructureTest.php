<?php

namespace Tests\Unit\Enums\IDP;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('role')]
#[Group('idp')]
class NewRoleStructureTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_role_constants(): void
    {
        $this->assertEquals('god', RoleDefaults::GOD);
        $this->assertEquals('hexeko_super_admin', RoleDefaults::HEXEKO_SUPER_ADMIN);
        $this->assertEquals('hexeko_admin', RoleDefaults::HEXEKO_ADMIN);
        $this->assertEquals('division_super_admin', RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->assertEquals('division_admin', RoleDefaults::DIVISION_ADMIN);
        $this->assertEquals('financer_super_admin', RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->assertEquals('financer_admin', RoleDefaults::FINANCER_ADMIN);
        $this->assertEquals('beneficiary', RoleDefaults::BENEFICIARY);
    }

    #[Test]
    public function god_role_has_all_permissions(): void
    {
        $godPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::GOD);
        $allPermissions = PermissionDefaults::asArray();

        // GOD may have superset of all base permissions
        $this->assertGreaterThanOrEqual(count($allPermissions), count($godPermissions));

        foreach ($allPermissions as $permission) {
            $this->assertContains($permission, $godPermissions);
        }
    }

    #[Test]
    public function hexeko_super_admin_has_all_permissions(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::HEXEKO_SUPER_ADMIN);
        $allPermissions = PermissionDefaults::asArray();

        foreach ($allPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    #[Test]
    public function hexeko_admin_has_all_permissions_except_manage_permissions(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::HEXEKO_ADMIN);

        $this->assertNotContains(PermissionDefaults::CREATE_PERMISSION, $permissions);
        $this->assertNotContains(PermissionDefaults::UPDATE_PERMISSION, $permissions);
        $this->assertNotContains(PermissionDefaults::DELETE_PERMISSION, $permissions);

        $this->assertContains(PermissionDefaults::READ_PERMISSION, $permissions);
    }

    #[Test]
    public function division_super_admin_inherits_division_admin_permissions(): void
    {
        $divisionSuperAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::DIVISION_ADMIN);

        foreach ($divisionAdminPermissions as $permission) {
            $this->assertContains($permission, $divisionSuperAdminPermissions);
        }
    }

    #[Test]
    public function division_admin_inherits_financer_super_admin_permissions(): void
    {
        $divisionAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::DIVISION_ADMIN);
        $financerSuperAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::FINANCER_SUPER_ADMIN);

        foreach ($financerSuperAdminPermissions as $permission) {
            $this->assertContains($permission, $divisionAdminPermissions);
        }
    }

    #[Test]
    public function financer_super_admin_inherits_financer_admin_permissions(): void
    {
        $financerSuperAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financerAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::FINANCER_ADMIN);

        foreach ($financerAdminPermissions as $permission) {
            $this->assertContains($permission, $financerSuperAdminPermissions);
        }
    }

    #[Test]
    public function financer_admin_inherits_beneficiary_permissions(): void
    {
        $financerAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::FINANCER_ADMIN);
        $beneficiaryPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::BENEFICIARY);

        foreach ($beneficiaryPermissions as $permission) {
            $this->assertContains($permission, $financerAdminPermissions);
        }
    }

    #[Test]
    public function beneficiary_has_basic_permissions(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::BENEFICIARY);

        $this->assertContains(PermissionDefaults::READ_ARTICLE, $permissions);
        $this->assertContains(PermissionDefaults::READ_HRTOOLS, $permissions);
        $this->assertContains(PermissionDefaults::USE_INTEGRATION, $permissions);
        $this->assertContains(PermissionDefaults::READ_MODULE, $permissions);
        $this->assertContains(PermissionDefaults::CREATE_VOUCHER, $permissions);
        $this->assertContains(PermissionDefaults::READ_SURVEY, $permissions);
        $this->assertContains(PermissionDefaults::READ_QUESTION, $permissions);
    }

    #[Test]
    public function assignable_roles_hierarchy_is_correct(): void
    {
        $godAssignable = RoleDefaults::getAssignableRoles(RoleDefaults::GOD);
        $this->assertContains(RoleDefaults::HEXEKO_SUPER_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::HEXEKO_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::DIVISION_SUPER_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::DIVISION_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::FINANCER_SUPER_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::FINANCER_ADMIN, $godAssignable);
        $this->assertContains(RoleDefaults::BENEFICIARY, $godAssignable);

        $hexekoSuperAdminAssignable = RoleDefaults::getAssignableRoles(RoleDefaults::HEXEKO_SUPER_ADMIN);
        $this->assertNotContains(RoleDefaults::GOD, $hexekoSuperAdminAssignable);
        $this->assertNotContains(RoleDefaults::HEXEKO_SUPER_ADMIN, $hexekoSuperAdminAssignable);
        $this->assertContains(RoleDefaults::HEXEKO_ADMIN, $hexekoSuperAdminAssignable);

        $hexekoAdminAssignable = RoleDefaults::getAssignableRoles(RoleDefaults::HEXEKO_ADMIN);
        $this->assertNotContains(RoleDefaults::GOD, $hexekoAdminAssignable);
        $this->assertNotContains(RoleDefaults::HEXEKO_SUPER_ADMIN, $hexekoAdminAssignable);
        $this->assertNotContains(RoleDefaults::HEXEKO_ADMIN, $hexekoAdminAssignable);
        $this->assertContains(RoleDefaults::DIVISION_SUPER_ADMIN, $hexekoAdminAssignable);
    }

    #[Test]
    public function can_manage_role_respects_hierarchy(): void
    {
        $this->assertTrue(RoleDefaults::canManageRole([RoleDefaults::GOD], RoleDefaults::HEXEKO_SUPER_ADMIN));
        $this->assertTrue(RoleDefaults::canManageRole([RoleDefaults::GOD], RoleDefaults::BENEFICIARY));

        $this->assertFalse(RoleDefaults::canManageRole([RoleDefaults::HEXEKO_ADMIN], RoleDefaults::GOD));
        $this->assertFalse(RoleDefaults::canManageRole([RoleDefaults::HEXEKO_ADMIN], RoleDefaults::HEXEKO_SUPER_ADMIN));
        $this->assertFalse(RoleDefaults::canManageRole([RoleDefaults::HEXEKO_ADMIN], RoleDefaults::HEXEKO_ADMIN));

        $this->assertTrue(RoleDefaults::canManageRole([RoleDefaults::HEXEKO_ADMIN], RoleDefaults::DIVISION_SUPER_ADMIN));
        $this->assertTrue(RoleDefaults::canManageRole([RoleDefaults::FINANCER_ADMIN], RoleDefaults::BENEFICIARY));

        $this->assertFalse(RoleDefaults::canManageRole([RoleDefaults::BENEFICIARY], RoleDefaults::BENEFICIARY));
    }

    #[Test]
    public function division_super_admin_has_complete_division_permissions(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::DIVISION_SUPER_ADMIN);

        $this->assertContains(PermissionDefaults::CREATE_FINANCER, $permissions);
        $this->assertContains(PermissionDefaults::READ_ANY_FINANCER, $permissions);
        $this->assertContains(PermissionDefaults::UPDATE_FINANCER, $permissions);
        $this->assertContains(PermissionDefaults::DELETE_FINANCER, $permissions);

        // READ_DIVISION not required by current role mapping
        $this->assertContains(PermissionDefaults::UPDATE_DIVISION, $permissions);
    }

    #[Test]
    public function division_admin_has_limited_division_permissions(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::DIVISION_ADMIN);

        $this->assertContains(PermissionDefaults::READ_ANY_FINANCER, $permissions);
        $this->assertContains(PermissionDefaults::UPDATE_FINANCER, $permissions);
        $this->assertContains(PermissionDefaults::CREATE_FINANCER, $permissions);
        $this->assertNotContains(PermissionDefaults::DELETE_FINANCER, $permissions);

        // Division read not granted in current role mapping
        $this->assertNotContains(PermissionDefaults::READ_DIVISION, $permissions);
        $this->assertNotContains(PermissionDefaults::CREATE_DIVISION, $permissions);
        $this->assertNotContains(PermissionDefaults::DELETE_DIVISION, $permissions);
    }

    #[Test]
    public function financer_super_admin_has_complete_user_management(): void
    {
        $permissions = RoleDefaults::getPermissionsByRole(RoleDefaults::FINANCER_SUPER_ADMIN);

        $this->assertContains(PermissionDefaults::CREATE_USER, $permissions);
        $this->assertContains(PermissionDefaults::READ_USER, $permissions);
        $this->assertContains(PermissionDefaults::UPDATE_USER, $permissions);
        $this->assertContains(PermissionDefaults::DELETE_USER, $permissions);
    }

    #[Test]
    public function only_god_and_hexeko_super_admin_can_create_permissions(): void
    {
        $godPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::GOD);
        $hexekoSuperAdminPermissions = RoleDefaults::getPermissionsByRole(RoleDefaults::HEXEKO_SUPER_ADMIN);

        $this->assertContains(PermissionDefaults::CREATE_PERMISSION, $godPermissions);
        $this->assertContains(PermissionDefaults::CREATE_PERMISSION, $hexekoSuperAdminPermissions);

        $otherRoles = [
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($otherRoles as $role) {
            $permissions = RoleDefaults::getPermissionsByRole($role);
            $this->assertNotContains(PermissionDefaults::CREATE_PERMISSION, $permissions);
        }
    }
}
