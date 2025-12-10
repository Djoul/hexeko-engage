<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Division;
use App\Models\Integration;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class IntegrationToggleTest extends ProtectedRouteTestCase
{
    private User $admin;

    private Division $division;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user with admin permissions
        $this->admin = User::factory()->create();

        // Create a division
        $this->division = Division::factory()->create();

        // Create an integration
        $this->integration = Integration::factory()->create();
    }

    #[Test]
    public function admin_can_toggle_integration_activation_for_division_from_inactive_to_active(): void
    {
        // Ensure the integration is not active for the division
        $this->assertDatabaseMissing('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
        ]);

        // API request to toggle integration for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.toggle', [
                'division_id' => $this->division->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration activated for division successfully',
                'active' => true,
            ]);

        // Assert integration is correctly activated for the division
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function admin_can_toggle_integration_activation_for_division_from_active_to_inactive(): void
    {
        // First, activate the integration for the division
        $this->division->integrations()->attach($this->integration->id, ['active' => true]);

        // Verify the integration is active
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);

        // API request to toggle integration for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.toggle', [
                'division_id' => $this->division->id,
                'integration_id' => $this->integration->id,
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integration deactivated for division successfully',
                'active' => false,
            ]);

        // Assert integration is correctly deactivated for the division
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function admin_can_bulk_toggle_multiple_integrations_for_division(): void
    {
        // Create additional integrations
        $integration2 = Integration::factory()->create();
        $integration3 = Integration::factory()->create();

        // Activate one of the integrations
        $this->division->integrations()->attach($integration2->id, ['active' => true]);

        // Verify integration2 is active
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $integration2->id,
            'active' => true,
        ]);

        // API request to bulk toggle integrations for the division
        $response = $this->actingAs($this->admin)
            ->postJson(route('division.integration.bulk-toggle', [
                'division_id' => $this->division->id,
                'integration_ids' => [
                    $this->integration->id,
                    $integration2->id,
                    $integration3->id,
                ],
            ]));

        // Assert the response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Integrations toggled for division successfully',
            ]);

        // Get the response data
        $responseData = $response->json();

        // Assert the toggle results match what we expect
        $this->assertTrue($responseData['results'][$this->integration->id]);
        $this->assertFalse($responseData['results'][$integration2->id]);
        $this->assertTrue($responseData['results'][$integration3->id]);

        // Assert integrations are correctly toggled in the database
        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $this->integration->id,
            'active' => true,
        ]);

        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $integration2->id,
            'active' => false,
        ]);

        $this->assertDatabaseHas('division_integration', [
            'division_id' => $this->division->id,
            'integration_id' => $integration3->id,
            'active' => true,
        ]);
    }
}
