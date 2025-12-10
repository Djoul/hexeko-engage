<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Artisan;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('role')]
class UserAssignableRolesTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/users';

    private Team $team;

    private $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create team and set permissions context
        $this->team = Team::factory()->create();
        setPermissionsTeamId($this->team->id);

        // Create financer
        $this->financer = Financer::factory()->create();

        // Create necessary roles for testing
        $this->createRoles();
    }

    private function createRoles(): void
    {
        $roles = [
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'api');
        }
    }

    #[Test]
    public function it_includes_assignable_roles_in_meta_for_financer_admin(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->assignRole(RoleDefaults::FINANCER_ADMIN);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'financer_admin',
        ]);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        $this->actingAs($user);

        $response = $this->getJson(self::URI.'?pagination=page');
        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'last_page',
                    'total',
                    'assignable_roles',
                ],
            ]);

        $assignableRoles = $response->json('meta.assignable_roles');
        $this->assertIsArray($assignableRoles);
        $this->assertCount(1, $assignableRoles);
        $this->assertEquals('beneficiary', $assignableRoles[0]['value']);
        $this->assertEquals('Beneficiary', $assignableRoles[0]['label']);
    }

    #[Test]
    public function it_includes_multiple_assignable_roles_for_division_admin(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->assignRole(RoleDefaults::DIVISION_ADMIN);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'division_admin',
        ]);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        $this->actingAs($user);

        $response = $this->getJson(self::URI);

        $response->assertOk();

        $assignableRoles = $response->json('meta.assignable_roles');
        $this->assertIsArray($assignableRoles);
        $this->assertCount(3, $assignableRoles);

        $roleValues = array_column($assignableRoles, 'value');
        $this->assertContains(RoleDefaults::FINANCER_SUPER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::FINANCER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::BENEFICIARY, $roleValues);
    }

    #[Test]
    public function it_includes_all_assignable_roles_for_hexeko_super_admin(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->assignRole(RoleDefaults::HEXEKO_SUPER_ADMIN);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'from' => now(),
            'role' => $user->roles->pluck('name')->first(),
        ]);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        Artisan::call('permission:cache-reset');
        $this->actingAs($user);

        $response = $this->getJson(self::URI);

        $response->assertOk();

        $assignableRoles = $response->json('meta.assignable_roles');
        $this->assertIsArray($assignableRoles);
        $this->assertCount(6, $assignableRoles); // All roles except HEXEKO_SUPER_ADMIN itself

        $roleValues = array_column($assignableRoles, 'value');
        $this->assertContains(RoleDefaults::HEXEKO_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::DIVISION_SUPER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::DIVISION_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::FINANCER_SUPER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::FINANCER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::BENEFICIARY, $roleValues);
    }

    #[Test]
    public function it_returns_empty_assignable_roles_for_beneficiary(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->assignRole(RoleDefaults::BENEFICIARY);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'from' => now(),
            'role' => $user->roles->pluck('name')->first(),
        ]);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        $this->actingAs($user);

        $response = $this->getJson(self::URI);

        $response->assertOk();

        $assignableRoles = $response->json('meta.assignable_roles');
        $this->assertIsArray($assignableRoles);
        $this->assertEmpty($assignableRoles);
    }

    #[Test]
    public function it_merges_assignable_roles_for_user_with_multiple_roles(): void
    {
        // Single role system: User has only DIVISION_ADMIN (highest level)
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->assignRole(RoleDefaults::DIVISION_ADMIN);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'from' => now(),
            'role' => $user->roles->pluck('name')->first(),
        ]);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        $this->actingAs($user);

        $response = $this->getJson(self::URI);

        $response->assertOk();

        $assignableRoles = $response->json('meta.assignable_roles');
        $this->assertIsArray($assignableRoles);

        // DIVISION_ADMIN can assign financer and beneficiary roles
        $roleValues = array_column($assignableRoles, 'value');
        $this->assertContains(RoleDefaults::BENEFICIARY, $roleValues);
        $this->assertContains(RoleDefaults::FINANCER_SUPER_ADMIN, $roleValues);
        $this->assertContains(RoleDefaults::FINANCER_ADMIN, $roleValues);

        // Check that roles are not duplicated
        $this->assertEquals(count($roleValues), count(array_unique($roleValues)));
    }
}
