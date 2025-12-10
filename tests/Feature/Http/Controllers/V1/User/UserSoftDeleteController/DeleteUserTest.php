<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserSoftDeleteController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class DeleteUserTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
    }

    #[Test]
    public function it_soft_deletes_user_with_single_financer(): void
    {
        $this->withExceptionHandling();

        // Create a user with a single financer
        $financer = Financer::factory()->create();
        $user = User::factory()->create();

        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Attach the auth user to the same financer so they have access
        $this->auth->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        // Reload financers relationship
        $this->auth->load('financers');

        // Ensure user is active for this financer
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'active' => true,
        ]);

        // User should not be soft deleted yet
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);

        // Set context for accessible financers
        Context::add('accessible_financers', [$financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);

        // User SHOULD BE soft deleted now
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_returns_403_when_user_has_multiple_financers(): void
    {
        // Create a user with multiple financers
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();
        $user = User::factory()->create();

        // Attach user to both financers
        $user->financers()->attach($financer1->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);
        $user->financers()->attach($financer2->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Attach the auth user to financer1 so they have access
        $this->auth->financers()->attach($financer1->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        // Reload financers relationship
        $this->auth->load('financers');

        // Set context for accessible financers
        Context::add('accessible_financers', [$financer1->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot delete user attached to multiple financers',
            ]);

        // User should NOT be soft deleted
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_returns_403_when_user_belongs_to_inaccessible_financer(): void
    {
        // Create two financers
        $accessibleFinancer = Financer::factory()->create();
        $inaccessibleFinancer = Financer::factory()->create();
        $user = User::factory()->create();

        // Attach user to inaccessible financer only
        $user->financers()->attach($inaccessibleFinancer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Attach auth user to accessible financer only
        $this->auth->financers()->attach($accessibleFinancer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        // Set context for accessible financers (not including user's financer)
        Context::add('accessible_financers', [$accessibleFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User belongs to a financer you do not have access to',
            ]);

        // User should NOT be soft deleted
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_soft_deletes_user_without_any_financer(): void
    {
        // Create user without any financer attachment
        $user = User::factory()->create();

        // Create a financer for auth user
        $financer = Financer::factory()->create();
        $this->auth->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        // Set context
        Context::add('accessible_financers', [$financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);

        // User SHOULD BE soft deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
