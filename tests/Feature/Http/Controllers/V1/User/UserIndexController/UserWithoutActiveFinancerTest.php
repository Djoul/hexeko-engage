<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Models\Financer;
use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('auth')]
#[Group('user')]
class UserWithoutActiveFinancerTest extends ProtectedRouteTestCase
{
    protected bool $checkActiveFinancer = true;

    #[Test]
    public function it_denies_access_to_user_without_active_financer(): void
    {
        // Create a user with no active financers
        $financer = Financer::factory()->create();
        $user = User::factory()->create();

        // Attach user to financer but inactive
        $user->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Try to access a protected route
        $response = $this->actingAs($user)
            ->getJson('/api/v1/users');

        // Should be denied access
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User must have at least one active financer',
            ]);
    }

    #[Test]
    public function it_allows_access_to_user_with_at_least_one_active_financer(): void
    {
        // Create a user with multiple financers
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();
        $user = User::factory()->create();

        // Attach user to both financers - one active, one inactive
        $user->financers()->attach($financer1->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);
        $user->financers()->attach($financer2->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);
        //        Context::add('financer_id', $financer2->id);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        // Try to access a protected route
        $response = $this->actingAs($user)
            ->getJson('/api/v1/users');

        // Should be allowed access
        $response->assertSuccessful();
    }

    #[Test]
    public function it_denies_access_to_user_with_no_financers(): void
    {
        // Create a user with no financers at all
        $user = User::factory()->create();

        // Try to access a protected route
        $response = $this->actingAs($user)
            ->getJson('/api/v1/users');

        // Should be denied access
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User must have at least one active financer',
            ]);
    }
}
