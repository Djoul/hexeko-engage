<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Integration;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class IntegrationActivationTest extends ProtectedRouteTestCase
{
    private User $admin;

    private Financer $financer;

    private Division $division;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user with admin permissions
        $this->admin = User::factory()->create();

        // Create a division linked to the financer
        $this->division = Division::factory()->create();

        // Create a financer
        $this->financer = Financer::factory()->create([
            'division_id' => $this->division->id,
        ]);

        // Create a integration
        $this->integration = Integration::factory()->create();
    }

    #[Test]
    public function admin_can_activate_a_integration_for_a_division(): void
    {
        // API request to activate integration for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.activate', [
                'division_id' => $this->division->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration activated for division successfully',
            ]);

        // Assert integration is correctly activated for the division
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_can_activate_a_integration_for_a_financer_if_it_is_enabled_in_at_least_one_division(): void
    {
        // First, activate the integration for the division
        $this->division->integrations()->attach($this->integration->id, ['active' => true]);

        // API request to activate integration for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.integration.activate', [
                'financer_id' => $this->financer->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration activated for financer successfully',
            ]);

        // Assert integration is correctly activated for the financer
        $this->assertDatabaseHas('financer_integration', [
            'financer_id' => $this->financer->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_cannot_activate_a_integration_for_a_financer_if_it_is_not_enabled_in_any_division(): void
    {
        // Ensure the integration is NOT activated in any division
        $this->assertDatabaseMissing('division_integration', [
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);

        // API request to activate integration for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.integration.activate', [
                'financer_id' => $this->financer->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Integration must be active in at least one division before activating it for a financer',
            ]);

        // Ensure integration was NOT activated for the financer
        $this->assertDatabaseMissing('financer_integration', [
            'financer_id' => $this->financer->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);
    }
}
