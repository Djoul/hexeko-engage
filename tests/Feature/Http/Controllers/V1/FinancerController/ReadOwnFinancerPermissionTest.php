<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\TeamTypes;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
#[Group('permissions')]
class ReadOwnFinancerPermissionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected bool $checkPermissions = true;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
        setPermissionsTeamId($this->team->id);
    }

    #[Test]
    public function user_with_read_any_financer_permission_can_access_any_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team, withContext: true);

        $readAnyFinancerPermission = Permission::where('name', PermissionDefaults::READ_ANY_FINANCER)->first();
        $user->givePermissionTo($readAnyFinancerPermission);

        $otherFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/financers/{$otherFinancer->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $otherFinancer->id,
            ],
        ]);
    }

    #[Test]
    public function user_with_read_own_financer_permission_can_access_their_own_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team);

        $readOwnFinancerPermission = Permission::where('name', PermissionDefaults::READ_OWN_FINANCER)->first();
        $user->givePermissionTo($readOwnFinancerPermission);

        // Attach user to financer via financer_user (active = true)
        $user->financers()->attach($financer->id, ['active' => true]);

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/financers/{$financer->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $financer->id,
            ],
        ]);
    }

    #[Test]
    public function user_with_read_own_financer_permission_cannot_access_other_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team);

        $readOwnFinancerPermission = Permission::where('name', PermissionDefaults::READ_OWN_FINANCER)->first();
        $user->givePermissionTo($readOwnFinancerPermission);

        // Attach user to their financer
        $user->financers()->attach($financer->id, ['active' => true]);

        // Create another financer the user is NOT attached to
        $otherFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/financers/{$otherFinancer->id}");

        // Assert
        $response->assertStatus(403);
    }

    #[Test]
    public function user_without_any_permission_cannot_access_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team);

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/financers/{$financer->id}");

        // Assert
        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_read_own_financer_permission_can_access_multiple_own_financers(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team);

        $readOwnFinancerPermission = Permission::where('name', PermissionDefaults::READ_OWN_FINANCER)->first();
        $user->givePermissionTo($readOwnFinancerPermission);

        // Attach user to both financers
        $user->financers()->attach($financer1->id, ['active' => true]);
        $user->financers()->attach($financer2->id, ['active' => true]);

        // Act - Test access to first financer
        $response1 = $this->actingAs($user)->getJson("/api/v1/financers/{$financer1->id}");

        // Assert
        $response1->assertStatus(200);
        $response1->assertJson([
            'data' => [
                'id' => $financer1->id,
            ],
        ]);

        // Act - Test access to second financer
        $response2 = $this->actingAs($user)->getJson("/api/v1/financers/{$financer2->id}");

        // Assert
        $response2->assertStatus(200);
        $response2->assertJson([
            'data' => [
                'id' => $financer2->id,
            ],
        ]);
    }

    #[Test]
    public function user_with_read_own_financer_permission_cannot_access_inactive_financer_attachment(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = $this->createAuthUser(team: $this->team);

        $readOwnFinancerPermission = Permission::where('name', PermissionDefaults::READ_OWN_FINANCER)->first();
        $user->givePermissionTo($readOwnFinancerPermission);

        // Attach user to financer but with active = false
        $user->financers()->attach($financer->id, ['active' => false]);

        // Act
        $response = $this->actingAs($user)->getJson("/api/v1/financers/{$financer->id}");

        // Assert
        $response->assertStatus(403);
    }
}
