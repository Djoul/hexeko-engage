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
class IntegrationDeactivationTest extends ProtectedRouteTestCase
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

        // Create an integration
        $this->integration = Integration::factory()->create();
    }

    #[Test]
    public function admin_can_deactivate_an_integration_for_a_division(): void
    {
        // First, activate the integration for the division
        $this->division->integrations()->attach($this->integration->id, ['active' => true]);

        // API request to deactivate integration for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.deactivate', [
                'division_id' => $this->division->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration deactivated for division successfully',
            ]);

        // Assert integration is correctly deactivated for the division
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function admin_can_deactivate_an_integration_for_a_financer(): void
    {
        // First, activate the integration for the division and financer
        $this->division->integrations()->attach($this->integration->id, ['active' => true]);
        $this->financer->integrations()->attach($this->integration->id, ['active' => true]);

        // API request to deactivate integration for the financer
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.integration.deactivate', [
                'financer_id' => $this->financer->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration deactivated for financer successfully',
            ]);

        // Assert integration is correctly deactivated for the financer
        $this->assertDatabaseHas('financer_integration', [
            'financer_id' => $this->financer->id,
            'integration_id' => $this->integration->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function deactivating_integration_for_division_also_deactivates_it_for_related_financers(): void
    {
        // First, activate the integration for the division and financer
        $this->division->integrations()->attach($this->integration->id, ['active' => true]);
        $this->financer->integrations()->attach($this->integration->id, ['active' => true]);

        // API request to deactivate integration for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.deactivate', [
                'division_id' => $this->division->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200);

        // Assert integration is correctly deactivated for the division
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => false,
        ]);

        // Assert integration is also deactivated for the financer
        $this->assertDatabaseHas('financer_integration', [
            'financer_id' => $this->financer->id,
            'integration_id' => $this->integration->id,
            'active' => false,
        ]);
    }
}
