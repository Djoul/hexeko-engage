<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Financer;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Context;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users', 'roles', 'model_has_roles', 'permissions', 'model_has_permissions'], scope: 'test')]
#[Group('user')]
class UserPipelineTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();

        setPermissionsTeamId($this->team->id);

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, $this->team);
        $this->actingAs($this->auth);

        Context::add('accessible_financers', $this->auth->financers->pluck('id')->toArray());

    }

    #[Test]
    public function it_filters_users_by_admin_status_when_is_admin_true(): void
    {
        // Arrange
        $adminRole = Role::firstOrCreate(['name' => RoleDefaults::FINANCER_ADMIN, 'guard_name' => 'api']);
        $beneficiaryRole = Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        $financer = $this->auth->financers()->first();

        $adminUser = User::factory()->create();
        $adminUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $adminUser->assignRole($adminRole);

        // Ensure the role assignment is persisted before continuing
        $adminUser->load('roles');

        $regularUser = User::factory()->create();
        $regularUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $regularUser->assignRole($beneficiaryRole);

        // Ensure the role assignment is persisted before continuing
        $regularUser->load('roles');

        $userWithNoRole = User::factory()->create();
        $userWithNoRole->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);

        request()->merge(['is_admin' => 'true']);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert - check that our created admin user is in the results
        $this->assertTrue($result->contains($adminUser));
        $this->assertFalse($result->contains($regularUser));
        $this->assertFalse($result->contains($userWithNoRole));
    }

    #[Test]
    public function it_filters_users_by_admin_status_when_is_admin_false(): void
    {
        // Arrange
        $adminRole = Role::firstOrCreate(['name' => RoleDefaults::FINANCER_ADMIN, 'guard_name' => 'api']);
        $beneficiaryRole = Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        $financer = $this->auth->financers()->first();

        $adminUser = User::factory()->create();
        $adminUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $adminUser->assignRole($adminRole);

        // Ensure the role assignment is persisted before continuing
        $adminUser->load('roles');

        $regularUser = User::factory()->create();
        $regularUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $regularUser->assignRole($beneficiaryRole);

        // Ensure the role assignment is persisted before continuing
        $regularUser->load('roles');

        $userWithNoRole = User::factory()->create();
        $userWithNoRole->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);

        request()->merge(['is_admin' => 'false']);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert - check that our non-admin users are in the results
        $this->assertFalse($result->contains($adminUser));
        $this->assertTrue($result->contains($regularUser));
        $this->assertTrue($result->contains($userWithNoRole));
    }

    #[Test]
    public function it_returns_all_users_when_is_admin_filter_not_provided(): void
    {
        // Arrange
        $adminRole = Role::firstOrCreate(['name' => RoleDefaults::FINANCER_ADMIN, 'guard_name' => 'api']);
        $beneficiaryRole = Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        $financer = $this->auth->financers()->first();

        $adminUser = User::factory()->create();
        $adminUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $adminUser->assignRole($adminRole);

        $regularUser = User::factory()->create();
        $regularUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $regularUser->assignRole($beneficiaryRole);

        $userWithNoRole = User::factory()->create();
        $userWithNoRole->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);

        // Act - No is_admin filter provided
        $result = User::query()->pipeFiltered()->get();

        // Assert - all our users should be in the results
        $this->assertTrue($result->contains($adminUser));
        $this->assertTrue($result->contains($regularUser));
        $this->assertTrue($result->contains($userWithNoRole));
    }

    #[Test]
    public function it_filters_only_by_given_role_when_is_admin_filter_is_invalid(): void
    {
        // Arrange
        request()->merge(['is_admin' => 'invalid_value']);

        // Act & Assert - Should throw validation exception
        $this->expectException(ValidationException::class);
        User::query()->pipeFiltered()->get();
    }

    #[Test]
    public function it_filters_admin_users_across_all_admin_role_types(): void
    {
        // Reset permission cache before test
        app()['cache']->forget('spatie.permission.cache');

        // Create or get a global team for role assignment
        $globalTeam = Team::firstOrCreate(
            ['type' => TeamTypes::GLOBAL],
            ['name' => 'Global Team', 'slug' => 'global-team']
        );

        // Set team context to global team
        setPermissionsTeamId($globalTeam->id);

        $financer = $this->auth->financers()->first();

        // Arrange - Create roles with proper guard
        $roleNames = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
        ];

        $adminUsers = [];

        foreach ($roleNames as $roleName) {
            // Create role if it doesn't exist
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api',
            ]);

            // Create user
            $user = User::factory()->create();
            $user->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);

            // Assign role with team context
            $user->assignRole($role);

            // Store user for later assertions
            $adminUsers[] = $user;
        }

        // Create a regular user with non-admin role
        $regularUser = User::factory()->create();
        $regularUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $regularUser->assignRole(Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']));

        // Clear team context before querying to ensure roles are queried correctly
        setPermissionsTeamId(null);

        // Set the filter parameter
        request()->merge(['is_admin' => 'true']);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert - check that all our admin users are in the results
        foreach ($adminUsers as $adminUser) {
            $foundInResults = $result->contains('id', $adminUser->id);

            // Get the role name for better error messages
            $roleName = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', User::class)
                ->where('model_has_roles.model_uuid', $adminUser->id)
                ->value('roles.name') ?? 'no role';

            $this->assertTrue($foundInResults, "Admin user {$adminUser->id} with role {$roleName} should be in results");
        }

        // Assert regular user is not in admin results
        $this->assertFalse($result->contains('id', $regularUser->id), 'Regular user should not be in admin results');
    }

    #[Test]
    public function it_can_combine_is_admin_filter_with_other_filters(): void
    {
        // Arrange
        $adminRole = Role::firstOrCreate(['name' => RoleDefaults::FINANCER_ADMIN, 'guard_name' => 'api']);
        $beneficiaryRole = Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        $financer = $this->auth->financers()->first();

        $enabledAdmin = User::factory()->create(['enabled' => true]);
        $enabledAdmin->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $enabledAdmin->assignRole($adminRole);

        $disabledAdmin = User::factory()->create(['enabled' => false]);
        $disabledAdmin->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $disabledAdmin->assignRole($adminRole);

        $enabledRegular = User::factory()->create(['enabled' => true]);
        $enabledRegular->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $enabledRegular->assignRole($beneficiaryRole);

        request()->merge([
            'is_admin' => 'true',
            'enabled' => 'true',
        ]);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert - check that only enabled admin is in results
        $this->assertTrue($result->contains($enabledAdmin));
        $this->assertFalse($result->contains($disabledAdmin));
        $this->assertFalse($result->contains($enabledRegular));
    }

    #[Test]
    public function it_filters_admin_by_context_financer(): void
    {
        // Arrange: Paul is admin of Financer A, but only beneficiary of Financer B
        $beneficiaryRole = Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        $financerA = $this->auth->financers()->first();
        $financerB = Financer::factory()->create();

        $paul = User::factory()->create();
        $paul->financers()->attach($financerA->id, [
            'role' => 'financer_admin', // Admin de A
            'from' => now(),
        ]);
        $paul->financers()->attach($financerB->id, [
            'role' => 'beneficiary', // Bénéficiaire de B
            'from' => now(),
        ]);
        $paul->assignRole($beneficiaryRole);
        $this->hydrateAuthorizationContext($paul);
        // Update accessible financers to include both

        // Act 1: Filter by Financer A with is_admin=true
        request()->merge(['financer_id' => $financerA->id, 'is_admin' => 'true']);
        $resultA = User::query()->pipeFiltered()->get();

        // Assert 1: Paul should appear (admin of A)
        $this->assertTrue(
            $resultA->contains($paul),
            'Paul should appear in is_admin=true for Financer A (he is admin)'
        );

        // Act 2: Filter by Financer B with is_admin=true
        request()->merge(['financer_id' => $financerB->id, 'is_admin' => 'true']);
        $resultB = User::query()->pipeFiltered()->get();

        // Assert 2: Paul should NOT appear (only beneficiary of B)
        $this->assertFalse(
            $resultB->contains($paul),
            'Paul should NOT appear in is_admin=true for Financer B (he is beneficiary)'
        );

        // Act 3: Filter by Financer B with is_admin=false
        request()->merge(['financer_id' => $financerB->id, 'is_admin' => 'false']);
        $resultBeneficiary = User::query()->pipeFiltered()->get();

        // Assert 3: Paul SHOULD appear (beneficiary of B)
        $this->assertTrue(
            $resultBeneficiary->contains($paul),
            'Paul should appear in is_admin=false for Financer B (he is beneficiary)'
        );
    }
}
