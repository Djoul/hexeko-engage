<?php

namespace Tests\Helpers\Traits;

use App\Models\Financer;
use Illuminate\Database\Eloquent\Model;

trait TestsFinancerSecurity
{
    /**
     * Assert that a user cannot access resources from a financer they don't have access to.
     *
     * Tests two security layers:
     * 1. Global scope protection (should return 404)
     * 2. Middleware protection (should return 403)
     *
     * @param  class-string  $modelClass
     * @param  string  $routeName  Route name without the action (e.g., 'sites', 'departments')
     * @param  string  $routeParameter  Route parameter name (defaults to route name)
     */
    protected function assertCannotAccessOtherFinancerResource(
        string $modelClass,
        string $routeName,
        ?string $routeParameter = null
    ): void {
        $routeParameter = $routeParameter ?? str_replace('-', '_', $routeName);

        $otherFinancer = Financer::factory()->create();

        // Test 1: Global scope protection (404)
        // Create a resource that belongs to another financer
        /** @var Model $otherResource */
        $otherResource = $modelClass::factory()->create(['financer_id' => $otherFinancer->id]);

        // When user tries to access it with their own financer_id,
        // the global scope should hide the resource (404)
        $response = $this->actingAs($this->auth)
            ->getJson(route("{$routeName}.show", [
                $routeParameter => $otherResource->id,
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(404);

        // Test 2: Middleware protection (403)
        // Create a resource that belongs to the user's financer
        /** @var Model $ownResource */
        $ownResource = $modelClass::factory()->create(['financer_id' => $this->financer->id]);

        // When user tries to access it by forcing another financer_id in URL,
        // the middleware should block access (403) because user doesn't have access to that financer
        $response = $this->actingAs($this->auth)
            ->getJson(route("{$routeName}.show", [
                $routeParameter => $ownResource->id,
                'financer_id' => $otherFinancer->id,
            ]));

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'FinancerAccessDeniedException']);
    }
}
