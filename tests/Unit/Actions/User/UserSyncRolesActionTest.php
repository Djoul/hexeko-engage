<?php

namespace Tests\Unit\Actions\User;

use App\Actions\User\Roles\UserSyncRolesAction;
use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('role')]
class UserSyncRolesActionTest extends TestCase
{
    use DatabaseTransactions;

    private UserSyncRolesAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UserSyncRolesAction;
    }

    #[Test]
    public function it_synchronizes_roles_for_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Définir le contexte team si nécessaire
        if ($user->team_id) {
            setPermissionsTeamId($user->team_id);
        }

        // Créer des rôles avec le même team_id que l'utilisateur
        $role1 = Role::create(['name' => 'test-role-1', 'guard_name' => 'api', 'team_id' => $user->team_id]);
        $role2 = Role::create(['name' => 'test-role-2', 'guard_name' => 'api', 'team_id' => $user->team_id]);

        // Assigner initialement role1
        $user->assignRole($role1);

        // Act - Synchroniser avec role2 (role1 sera remplacé par role2 dans le système single-role)
        $result = $this->action->execute($user, $role2->name);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole($role2));
        $this->assertFalse($user->hasRole($role1)); // role1 est remplacé dans système single-role
    }

    #[Test]
    public function it_can_assign_beneficiary_role_replacing_existing_role(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Définir le contexte team si nécessaire
        if ($user->team_id) {
            setPermissionsTeamId($user->team_id);
        }

        // Assurer que le rôle Beneficiary existe
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->where('team_id', $user->team_id)->exists()) {
            Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api', 'team_id' => $user->team_id]);
        }

        // Créer un autre rôle
        $role1 = Role::create(['name' => 'test-role-1', 'guard_name' => 'api', 'team_id' => $user->team_id]);

        // Assigner le rôle role1 initialement
        $user->assignRole($role1);

        // Act - Synchroniser avec beneficiary (role1 sera remplacé dans système single-role)
        $result = $this->action->execute($user, RoleDefaults::BENEFICIARY);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole(RoleDefaults::BENEFICIARY));
        $this->assertFalse($user->hasRole($role1)); // role1 remplacé par beneficiary
        $this->assertEquals(1, $user->roles()->count()); // Single role system
    }

    #[Test]
    public function it_assigns_single_beneficiary_role(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Définir le contexte team si nécessaire
        if ($user->team_id) {
            setPermissionsTeamId($user->team_id);
        }

        // Assurer que le rôle Beneficiary existe
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->where('team_id', $user->team_id)->exists()) {
            Role::firstOrCreate(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api', 'team_id' => $user->team_id]);
        }

        // Act - Assigner Beneficiary dans le système single-role
        $result = $this->action->execute($user, RoleDefaults::BENEFICIARY);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole(RoleDefaults::BENEFICIARY));
        $this->assertEquals(1, $user->roles()->count()); // Single role only
    }

    #[Test]
    public function it_handles_team_context_when_synchronizing_roles(): void
    {
        // Arrange
        $team = ModelFactory::createTeam();
        $user = ModelFactory::createUser(['team_id' => $team->id]);

        // Créer un rôle avec team_id
        $role1 = Role::create(['name' => 'team-role-1', 'guard_name' => 'api', 'team_id' => $team->id]);

        // Définir le contexte de l'équipe
        setPermissionsTeamId($team->id);

        // Act - Assigner un seul rôle dans le système single-role
        $result = $this->action->execute($user, $role1->name);

        // Assert
        $this->assertTrue($result);
        $this->assertTrue($user->hasRole($role1));
        $this->assertEquals(1, $user->roles()->count()); // Single role system

        // Nettoyer le contexte
        setPermissionsTeamId(null);
    }

    #[Test]
    public function it_updates_financer_user_pivot_table_when_synchronizing_roles(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Créer l'utilisateur sans financer d'abord
        $user = ModelFactory::createUser([]);

        // Attacher manuellement le financer comme actif avec un rôle initial
        $user->financers()->attach($financer->id, [
            'active' => true,
            'role' => 'old-role', // Single role system
            'from' => now(),
        ]);

        if ($user->team_id) {
            setPermissionsTeamId($user->team_id);
        }

        // Créer un nouveau rôle
        $newRole = Role::create(['name' => 'new-role', 'guard_name' => 'api', 'team_id' => $user->team_id]);

        // Simuler un contexte avec financer_id
        Context::add('financer_id', $financer->id);

        // Act - Assigner le nouveau rôle (remplace l'ancien)
        $result = $this->action->execute($user, $newRole->name);

        // Assert
        $this->assertTrue($result);

        // Vérifier que l'utilisateur a bien le nouveau rôle assigné
        $this->assertTrue($user->hasRole($newRole));
        $this->assertEquals(1, $user->roles()->count()); // Single role system

        // Recharger le user pour avoir les données à jour
        $user->load('financers');

        // Vérifier que le rôle est mis à jour dans la table pivot (single role)
        $pivotData = $user->financers()->where('financer_id', $financer->id)->first()->pivot;
        $this->assertNotNull($pivotData->role);
        $this->assertEquals($newRole->name, $pivotData->role); // Single role comparison
    }

    #[Test]
    public function it_preserves_other_users_when_syncing_division_admin_role(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Create existing users in the division
        $existingUser1 = ModelFactory::createUser([
            'email' => 'existing1@test.com',
            'financers' => [['financer' => $financer1, 'active' => true]],
        ]);
        $existingUser2 = ModelFactory::createUser([
            'email' => 'existing2@test.com',
            'financers' => [['financer' => $financer2, 'active' => true]],
        ]);

        // Create the admin user
        $adminUser = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [['financer' => $financer1, 'active' => true]],
        ]);

        // Set permissions team context if needed
        if ($adminUser->team_id) {
            setPermissionsTeamId($adminUser->team_id);
        }

        // Setup roles
        Role::firstOrCreate(
            ['name' => RoleDefaults::BENEFICIARY, 'team_id' => $adminUser->team_id],
            ['guard_name' => 'api']
        );
        Role::firstOrCreate(
            ['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $adminUser->team_id],
            ['guard_name' => 'api']
        );

        $adminUser->assignRole(RoleDefaults::BENEFICIARY);
        Context::add('financer_id', $financer1->id);

        // Act - Single role system: assign only DIVISION_ADMIN (replaces BENEFICIARY)
        $result = $this->action->execute($adminUser, RoleDefaults::DIVISION_ADMIN);

        // Assert
        $this->assertTrue($result);

        // Verify existing users still have their financer relations
        $existingUser1->refresh();
        $this->assertTrue($existingUser1->financers->contains($financer1));

        $existingUser2->refresh();
        $this->assertTrue($existingUser2->financers->contains($financer2));

        // Verify admin is ONLY attached to financer1, not all division financers
        $adminUser->refresh();
        $this->assertCount(1, $adminUser->financers,
            'Division Admin should only be attached to their original financer'
        );
        $this->assertTrue($adminUser->financers->contains($financer1));
        $this->assertFalse($adminUser->financers->contains($financer2),
            'Division Admin should not be automatically attached to other division financers'
        );

        // Single role system: Admin has ONLY DIVISION_ADMIN role
        $this->assertTrue($adminUser->hasRole(RoleDefaults::DIVISION_ADMIN));
        $this->assertFalse($adminUser->hasRole(RoleDefaults::BENEFICIARY)); // No dual roles
        $this->assertEquals(1, $adminUser->roles()->count());

        // Verify role in pivot for admin's own financer (single role)
        $pivotFinancer1 = $adminUser->financers()->where('financer_id', $financer1->id)->first()->pivot;
        $this->assertEquals(RoleDefaults::DIVISION_ADMIN, $pivotFinancer1->role);
    }

    #[Test]
    public function it_preserves_other_users_when_syncing_division_super_admin_role(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Create existing users in the division
        $existingUser1 = ModelFactory::createUser([
            'email' => 'existing1@test.com',
            'financers' => [['financer' => $financer1, 'active' => true]],
        ]);
        $existingUser2 = ModelFactory::createUser([
            'email' => 'existing2@test.com',
            'financers' => [['financer' => $financer2, 'active' => true]],
        ]);

        // Create the super admin user
        $superAdminUser = ModelFactory::createUser([
            'email' => 'superadmin@test.com',
            'financers' => [['financer' => $financer1, 'active' => true]],
        ]);

        // Set permissions team context if needed
        if ($superAdminUser->team_id) {
            setPermissionsTeamId($superAdminUser->team_id);
        }

        // Setup roles
        Role::firstOrCreate(
            ['name' => RoleDefaults::BENEFICIARY, 'team_id' => $superAdminUser->team_id],
            ['guard_name' => 'api']
        );
        Role::firstOrCreate(
            ['name' => RoleDefaults::DIVISION_SUPER_ADMIN, 'team_id' => $superAdminUser->team_id],
            ['guard_name' => 'api']
        );

        $superAdminUser->assignRole(RoleDefaults::BENEFICIARY);
        Context::add('financer_id', $financer1->id);

        // Act - Single role system: assign only DIVISION_SUPER_ADMIN (replaces BENEFICIARY)
        $result = $this->action->execute($superAdminUser, RoleDefaults::DIVISION_SUPER_ADMIN);

        // Assert
        $this->assertTrue($result);

        // Verify existing users still have their financer relations
        $existingUser1->refresh();
        $this->assertTrue($existingUser1->financers->contains($financer1));

        $existingUser2->refresh();
        $this->assertTrue($existingUser2->financers->contains($financer2));

        // Verify super admin is ONLY attached to financer1, not all division financers
        $superAdminUser->refresh();
        $this->assertCount(1, $superAdminUser->financers,
            'Division Super Admin should only be attached to their original financer'
        );
        $this->assertTrue($superAdminUser->financers->contains($financer1));
        $this->assertFalse($superAdminUser->financers->contains($financer2),
            'Division Super Admin should not be automatically attached to other division financers'
        );

        // Single role system: Super Admin has ONLY DIVISION_SUPER_ADMIN role
        $this->assertTrue($superAdminUser->hasRole(RoleDefaults::DIVISION_SUPER_ADMIN));
        $this->assertFalse($superAdminUser->hasRole(RoleDefaults::BENEFICIARY)); // No dual roles
        $this->assertEquals(1, $superAdminUser->roles()->count());

        // Verify role in pivot for super admin's own financer (single role)
        $pivotFinancer1 = $superAdminUser->financers()->where('financer_id', $financer1->id)->first()->pivot;
        $this->assertEquals(RoleDefaults::DIVISION_SUPER_ADMIN, $pivotFinancer1->role);
    }

    #[Test]
    public function it_updates_role_only_for_specified_financer_not_all_financers(): void
    {
        // Arrange - User Paul with DIFFERENT roles for DIFFERENT financers
        $division = ModelFactory::createDivision();
        $financerA = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer A']);
        $financerB = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer B']);

        // Paul is attached to both financers with BENEFICIARY role initially
        $paul = ModelFactory::createUser([
            'email' => 'paul@test.com',
            'financers' => [
                ['financer' => $financerA, 'active' => true, 'role' => RoleDefaults::BENEFICIARY],
                ['financer' => $financerB, 'active' => true, 'role' => RoleDefaults::BENEFICIARY],
            ],
        ]);

        // Set permissions team context
        if ($paul->team_id) {
            setPermissionsTeamId($paul->team_id);
        }

        // Create DIVISION_ADMIN role
        Role::firstOrCreate(
            ['name' => RoleDefaults::DIVISION_ADMIN, 'team_id' => $paul->team_id],
            ['guard_name' => 'api']
        );

        // Set context to Financer A (Paul will become admin ONLY for Financer A)
        Context::add('financer_id', $financerA->id);

        // Act - Assign DIVISION_ADMIN to Paul in context of Financer A
        $result = $this->action->execute($paul, RoleDefaults::DIVISION_ADMIN);

        // Assert
        $this->assertTrue($result);
        $paul->refresh();

        // Paul should have DIVISION_ADMIN role for Financer A
        $pivotA = $paul->financers()->where('financer_id', $financerA->id)->first()->pivot;
        $this->assertEquals(RoleDefaults::DIVISION_ADMIN, $pivotA->role,
            'Paul should be DIVISION_ADMIN for Financer A'
        );

        // Paul should STILL be BENEFICIARY for Financer B (unchanged)
        $pivotB = $paul->financers()->where('financer_id', $financerB->id)->first()->pivot;
        $this->assertEquals(RoleDefaults::BENEFICIARY, $pivotB->role,
            'Paul should remain BENEFICIARY for Financer B (different role per financer)'
        );

        // Paul has the DIVISION_ADMIN Spatie role globally
        $this->assertTrue($paul->hasRole(RoleDefaults::DIVISION_ADMIN));
    }
}
