<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\ModulesCategories;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
#[Group('permissions')]
final class ModulePermissionsTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected bool $checkPermissions = true;

    private Division $division;

    private Financer $financer;

    private Module $module;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create team for role assignments
        $this->team = ModelFactory::createTeam([
            'name' => 'Test Team',
        ]);

        // Set team ID for permissions
        setPermissionsTeamId($this->team->id);

        // Create test data hierarchy
        $this->division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $this->financer = ModelFactory::createFinancer([
            'division_id' => $this->division->id,
            'name' => 'Test Financer',
            'status' => 'active',
        ]);

        $this->module = Module::factory()->create([
            'name' => 'Test Module',
            'description' => 'A module for testing permissions',
            'is_core' => false,
        ]);
    }

    // ==========================================
    // HEXEKO_ADMIN Role Tests (Full Module CRUD)
    // ==========================================

    #[Test]
    public function hexeko_admin_can_create_module(): void
    {
        // Arrange
        $admin = ModelFactory::createUser([
            'email' => 'hexeko.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::HEXEKO_ADMIN);
        $admin->assignRole(RoleDefaults::HEXEKO_ADMIN);
        $this->hydrateAuthorizationContext($admin);

        // Act
        $response = $this->actingAs($admin)->postJson('/api/v1/modules', [
            'name' => [
                'en' => 'New Module',
                'fr' => 'Nouveau Module',
            ],
            'description' => [
                'en' => 'Created by HEXEKO_ADMIN',
                'fr' => 'Créé par HEXEKO_ADMIN',
            ],
            'category' => ModulesCategories::PURCHASING_POWER,
            'is_core' => false,
        ]);

        // Assert
        $response->assertCreated();

        $this->assertDatabaseHas('modules', [
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);
    }

    #[Test]
    public function hexeko_admin_can_update_module(): void
    {
        // Arrange
        $admin = ModelFactory::createUser([
            'email' => 'hexeko.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::HEXEKO_ADMIN);
        $admin->assignRole(RoleDefaults::HEXEKO_ADMIN);
        $this->hydrateAuthorizationContext($admin);

        // Act
        $response = $this->actingAs($admin)->putJson("/api/v1/modules/{$this->module->id}", [
            'id' => $this->module->id,
            'name' => [
                'en' => 'Updated Module Name',
                'fr' => 'Nom de Module Mis à Jour',
            ],
            'description' => [
                'en' => 'Updated description',
                'fr' => 'Description mise à jour',
            ],
            'category' => ModulesCategories::WELLBEING,
            'is_core' => false,
        ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('modules', [
            'id' => $this->module->id,
            'category' => ModulesCategories::WELLBEING,
        ]);
    }

    #[Test]
    public function hexeko_admin_can_delete_module(): void
    {
        // Arrange
        $admin = ModelFactory::createUser([
            'email' => 'hexeko.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::HEXEKO_ADMIN);
        $admin->assignRole(RoleDefaults::HEXEKO_ADMIN);
        $this->hydrateAuthorizationContext($admin);

        $moduleToDelete = Module::factory()->create([
            'name' => 'Module to Delete',
            'is_core' => false,
        ]);

        // Act
        $response = $this->actingAs($admin)->deleteJson("/api/v1/modules/{$moduleToDelete->id}");

        // Assert
        $response->assertStatus(204);

        // Verify soft delete
        $this->assertDatabaseHas('modules', [
            'id' => $moduleToDelete->id,
        ]);
        $this->assertNotNull(Module::withTrashed()->find($moduleToDelete->id)->deleted_at);
    }

    // ==========================================
    // DIVISION_SUPER_ADMIN Role Tests
    // ==========================================

    #[Test]
    public function division_super_admin_cannot_activate_module_for_division(): void
    {
        // Arrange
        $divisionAdmin = ModelFactory::createUser([
            'email' => 'division.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($divisionAdmin);

        // Act
        $response = $this->actingAs($divisionAdmin)->postJson('/api/v1/modules/division/activate', [
            'module_id' => $this->module->id,
            'division_id' => $this->division->id,
        ]);

        // Assert: Only HEXEKO_ADMIN can manage division modules
        $response->assertForbidden();
    }

    #[Test]
    public function division_super_admin_cannot_deactivate_module_for_division(): void
    {
        // Arrange
        $divisionAdmin = ModelFactory::createUser([
            'email' => 'division.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($divisionAdmin);

        // Activate module first
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // Act
        $response = $this->actingAs($divisionAdmin)->postJson('/api/v1/modules/division/deactivate', [
            'module_id' => $this->module->id,
            'division_id' => $this->division->id,
        ]);

        // Assert: Only HEXEKO_ADMIN can manage division modules
        $response->assertForbidden();
    }

    #[Test]
    public function division_super_admin_cannot_update_division_modules(): void
    {
        // Arrange
        $divisionAdmin = ModelFactory::createUser([
            'email' => 'division.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($divisionAdmin);

        $module2 = Module::factory()->create(['name' => 'Module 2', 'is_core' => false]);

        // Act
        $response = $this->actingAs($divisionAdmin)->putJson("/api/v1/divisions/{$this->division->id}/modules", [
            'core_package_price' => 5000,
            'modules' => [
                [
                    'id' => $this->module->id,
                    'active' => true,
                    'price_per_beneficiary' => 1000,
                ],
                [
                    'id' => $module2->id,
                    'active' => true,
                    'price_per_beneficiary' => 1500,
                ],
            ],
        ]);

        // Assert: Only HEXEKO_ADMIN can manage division modules
        $response->assertForbidden();
    }

    #[Test]
    public function division_super_admin_can_manage_financer_modules(): void
    {
        // Arrange: DIVISION_SUPER_ADMIN has MANAGE_FINANCER_MODULES permission
        $divisionSuperAdmin = ModelFactory::createUser([
            'email' => 'division.super.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionSuperAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($divisionSuperAdmin);

        // Activate module for division first
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // Act
        $response = $this->actingAs($divisionSuperAdmin)->postJson('/api/v1/modules/financer/activate', [
            'module_id' => $this->module->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'message' => 'Module activated for financer successfully',
            ]);

        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function division_super_admin_cannot_create_or_delete_modules(): void
    {
        // Arrange
        $divisionAdmin = ModelFactory::createUser([
            'email' => 'division.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::DIVISION_SUPER_ADMIN);
        $divisionAdmin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($divisionAdmin);

        // Act: Try to create module
        $createResponse = $this->actingAs($divisionAdmin)->postJson('/api/v1/modules', [
            'name' => ['en' => 'Unauthorized Module'],
            'description' => ['en' => 'Should not be created'],
            'category' => ModulesCategories::PURCHASING_POWER,
            'is_core' => false,
        ]);

        // Act: Try to update module
        $updateResponse = $this->actingAs($divisionAdmin)->putJson("/api/v1/modules/{$this->module->id}", [
            'id' => $this->module->id,
            'name' => ['en' => 'Updated Name'],
            'description' => ['en' => 'Updated description'],
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);

        // Act: Try to delete module
        $deleteResponse = $this->actingAs($divisionAdmin)->deleteJson("/api/v1/modules/{$this->module->id}");

        // Assert
        $createResponse->assertForbidden();
        $updateResponse->assertOk(); // DIVISION_SUPER_ADMIN has MANAGE_FINANCER_MODULES so can update
        $deleteResponse->assertForbidden();
    }

    // ==========================================
    // FINANCER_SUPER_ADMIN Role Tests
    // ==========================================

    #[Test]
    public function financer_super_admin_cannot_activate_module_for_financer(): void
    {
        // Arrange
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($financerAdmin);

        // Activate module for division first
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // Act
        $response = $this->actingAs($financerAdmin)->postJson('/api/v1/modules/financer/activate', [
            'module_id' => $this->module->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert: Only DIVISION_ADMIN can manage financer modules
        $response->assertForbidden();
    }

    #[Test]
    public function financer_super_admin_cannot_update_financer_modules(): void
    {
        // Arrange
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($financerAdmin);

        // Activate modules for division
        $this->division->modules()->attach($this->module->id, ['active' => true]);

        // Act
        $response = $this->actingAs($financerAdmin)->putJson("/api/v1/financers/{$this->financer->id}/modules", [
            'core_package_price' => 5000,
            'modules' => [
                [
                    'id' => $this->module->id,
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 2000,
                ],
            ],
        ]);

        // Assert: Only DIVISION_ADMIN can manage financer modules
        $response->assertForbidden();
    }

    #[Test]
    public function financer_super_admin_cannot_manage_division_modules(): void
    {
        // Arrange
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($financerAdmin);

        // Act: Try to activate module for division
        $response = $this->actingAs($financerAdmin)->postJson('/api/v1/modules/division/activate', [
            'module_id' => $this->module->id,
            'division_id' => $this->division->id,
        ]);

        // Assert
        $response->assertForbidden();
    }

    #[Test]
    public function financer_super_admin_cannot_create_or_delete_modules(): void
    {
        // Arrange
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer.admin@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->hydrateAuthorizationContext($financerAdmin);

        // Act: Try to create module
        $createResponse = $this->actingAs($financerAdmin)->postJson('/api/v1/modules', [
            'name' => ['en' => 'Unauthorized Module'],
            'description' => ['en' => 'Should not be created'],
            'category' => ModulesCategories::PURCHASING_POWER,
            'is_core' => false,
        ]);

        // Act: Try to update module
        $updateResponse = $this->actingAs($financerAdmin)->putJson("/api/v1/modules/{$this->module->id}", [
            'id' => $this->module->id,
            'name' => ['en' => 'Updated Name'],
            'description' => ['en' => 'Updated description'],
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);

        // Act: Try to delete module
        $deleteResponse = $this->actingAs($financerAdmin)->deleteJson("/api/v1/modules/{$this->module->id}");

        // Assert
        $createResponse->assertForbidden();
        $updateResponse->assertForbidden();
        $deleteResponse->assertForbidden();
    }

    // ==========================================
    // Negative Tests - Unauthorized Access
    // ==========================================

    #[Test]
    public function beneficiary_cannot_manage_any_modules(): void
    {
        // Arrange
        $beneficiary = ModelFactory::createUser([
            'email' => 'beneficiary@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::BENEFICIARY);
        $beneficiary->assignRole(RoleDefaults::BENEFICIARY);
        $this->hydrateAuthorizationContext($beneficiary);

        // Act: Try various module operations
        $createResponse = $this->actingAs($beneficiary)->postJson('/api/v1/modules', [
            'name' => ['en' => 'Unauthorized Module'],
            'description' => ['en' => 'Should not be created'],
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);

        $updateResponse = $this->actingAs($beneficiary)->putJson("/api/v1/modules/{$this->module->id}", [
            'id' => $this->module->id,
            'name' => ['en' => 'Updated Name'],
            'description' => ['en' => 'Updated description'],
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);

        $deleteResponse = $this->actingAs($beneficiary)->deleteJson("/api/v1/modules/{$this->module->id}");

        $activateDivisionResponse = $this->actingAs($beneficiary)->postJson('/api/v1/modules/division/activate', [
            'module_id' => $this->module->id,
            'division_id' => $this->division->id,
        ]);

        $activateFinancerResponse = $this->actingAs($beneficiary)->postJson('/api/v1/modules/financer/activate', [
            'module_id' => $this->module->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert: All operations forbidden
        $createResponse->assertForbidden();
        $updateResponse->assertForbidden();
        $deleteResponse->assertForbidden();
        $activateDivisionResponse->assertForbidden();
        $activateFinancerResponse->assertForbidden();
    }

    #[Test]
    public function financer_admin_cannot_manage_modules(): void
    {
        // Arrange
        $financerAdmin = ModelFactory::createUser([
            'email' => 'financer.regular@test.com',
            'team_id' => $this->team->id,
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->ensureRoleExists(RoleDefaults::FINANCER_ADMIN);
        $financerAdmin->assignRole(RoleDefaults::FINANCER_ADMIN);
        $this->hydrateAuthorizationContext($financerAdmin);

        // Act: Try to activate module for financer
        $response = $this->actingAs($financerAdmin)->postJson('/api/v1/modules/financer/activate', [
            'module_id' => $this->module->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $response->assertForbidden();
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    private function ensureRoleExists(string $roleName): Role
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'api')
            ->where('team_id', $this->team->id)
            ->first();

        if (! $role) {
            $role = ModelFactory::createRole([
                'name' => $roleName,
                'guard_name' => 'api',
                'team_id' => $this->team->id,
            ]);

            // Create and assign permissions for this role
            $permissions = RoleDefaults::getPermissionsByRole($roleName);
            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'api'],
                    ['is_protected' => true]
                );
                $role->givePermissionTo($permission);
            }
        }

        return $role;
    }
}
