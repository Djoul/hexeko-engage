<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserRolesController;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Str;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('role')]
class UserRolesSyncTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Créer les permissions nécessaires si elles n'existent pas

        Permission::firstOrCreate(
            ['name' => PermissionDefaults::MANAGE_USER_ROLES, 'guard_name' => 'api']
        );

        // Créer l'utilisateur auth avec la permission
        $this->auth = $this->createAuthUser();
        $this->auth->givePermissionTo(PermissionDefaults::MANAGE_USER_ROLES);
    }

    #[Test]
    public function it_synchronizes_user_role_successfully(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Créer un rôle
        $role = Role::create(['name' => 'test-role-1', 'guard_name' => 'api']);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => $role->name,
            ]);

        // Assert
        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue($user->fresh()->hasRole($role));
    }

    #[Test]
    public function it_validates_user_id_exists(): void
    {
        // Arrange
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$nonExistentId}/roles/sync", [
                'role' => 'admin',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);
    }

    #[Test]
    public function it_validates_role_is_string(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act - send an array instead of string
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => ['not-a-string'],
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function it_validates_role_exists(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => 'non-existent-role',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    #[Test]
    public function it_replaces_existing_role_with_new_role(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Assurer que le rôle existe
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->exists()) {
            Role::create(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        }

        $newRole = Role::create(['name' => 'new-role', 'guard_name' => 'api']);

        // Assigner le rôle Beneficiary initialement
        $user->assignRole(RoleDefaults::BENEFICIARY);

        // Act - sync new role should REPLACE beneficiary
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => $newRole->name,
            ]);

        // Assert
        $response->assertOk();
        $this->assertTrue($user->fresh()->hasRole($newRole));
        // User should have only ONE role now
        $this->assertCount(1, $user->fresh()->roles);
    }

    #[Test]
    public function it_handles_team_scoped_roles(): void
    {
        // Arrange
        $team = ModelFactory::createTeam();
        $user = ModelFactory::createUser(['team_id' => $team->id]);

        // Créer un rôle avec team_id
        $teamRole = Role::create(['name' => 'team-role-1', 'guard_name' => 'api', 'team_id' => $team->id]);

        // Définir le contexte de l'équipe
        setPermissionsTeamId($team->id);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => $teamRole->name,
            ]);

        // Assert
        $response->assertOk();
        $this->assertTrue($user->fresh()->hasRole($teamRole));

        // Nettoyer
        setPermissionsTeamId(null);
    }

    #[Test]
    public function it_returns_error_for_invalid_uuid(): void
    {
        // Arrange - create a valid role for the request
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'api']);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/users/invalid-uuid/roles/sync', [
                'role' => $role->name,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);
    }

    #[Test]
    public function it_can_sync_to_beneficiary_role(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Assurer que le rôle existe
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->exists()) {
            Role::create(['name' => RoleDefaults::BENEFICIARY, 'guard_name' => 'api']);
        }

        // Create another role and assign it first
        $otherRole = Role::create(['name' => 'other-role', 'guard_name' => 'api']);
        $user->assignRole($otherRole);

        // Act - Sync to Beneficiary role (replaces other-role)
        $response = $this->actingAs($this->auth)
            ->postJson("/api/v1/users/{$user->id}/roles/sync", [
                'role' => RoleDefaults::BENEFICIARY,
            ]);

        // Assert
        $response->assertOk();
        $this->assertTrue($user->fresh()->hasRole(RoleDefaults::BENEFICIARY));
        // User should have only ONE role
        $this->assertCount(1, $user->fresh()->roles);
    }

    #[Test]
    public function it_resolves_team_id_for_invited_user_with_null_team_id(): void
    {
        // Arrange - Ensure global team exists
        $globalTeam = Team::whereType(TeamTypes::GLOBAL)->first();
        if (! $globalTeam) {
            $globalTeam = ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
        }

        // Create invited user with NULL team_id in database (simulating the bug scenario)
        $invitedUser = ModelFactory::createUser([
            'invitation_status' => 'pending',
            'invitation_token' => Str::random(32),
            'invitation_expires_at' => now()->addDays(7),
            'invited_at' => now(),
            'team_id' => null, // Explicitly NULL to reproduce the bug
        ]);

        // Verify user is invited
        $this->assertTrue($invitedUser->isInvitedUser(), 'User should be in invited state');

        // Assert - The accessor should return global team ID instead of NULL
        $this->assertNotNull($invitedUser->team_id, 'team_id should not be null via accessor');
        $this->assertEquals($globalTeam->id, $invitedUser->team_id, 'team_id accessor should return global team ID');

        // This means Spatie Permission can now find roles for this user
        // because user->team_id is no longer NULL
    }
}
