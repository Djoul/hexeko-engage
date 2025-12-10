<?php

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Models\CreditBalance;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('credit')]
#[Group('internal-communication')]
#[Group('article')]
class TokenConsumptionQuotaIntegrationTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_updates_quota_after_token_consumption(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        // Create credit balance using factory for consistency
        $creditBalance = CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 100000,
                'context' => [
                    'initial_quota' => 100000,
                    'consumed' => 0,
                ],
            ]);

        // Act - Check initial quota
        $response = $this->actingAs($user)
            ->getJson('/api/v1/internal-communication/articles');

        // Assert - Initial quota is correct
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 100000,
                    'consumed' => 0,
                    'remaining' => 100000,
                    'percentage_used' => 0.0,
                ],
            ],
        ]);

        // Act - Update credit balance to simulate consumption
        $creditBalance->update([
            'balance' => 75000,
            'context' => [
                'initial_quota' => 100000,
                'consumed' => 25000,
            ],
        ]);

        // Act - Check quota after consumption
        $response = $this->actingAs($user)
            ->getJson('/api/v1/internal-communication/articles');

        // Assert - Quota reflects consumption
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 100000,
                    'consumed' => 25000,
                    'remaining' => 75000,
                    'percentage_used' => 25.0,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_handles_consumption_that_exceeds_quota(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;
        // Create credit balance with specific values using factory
        CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 10000,
                'context' => [
                    'initial_quota' => 50000,
                    'consumed' => 40000, // 80% used
                ],
            ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/v1/internal-communication/articles');

        // Assert
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 50000,
                    'consumed' => 40000,
                    'remaining' => 10000,
                    'percentage_used' => 80.0,
                ],
            ],
        ]);
    }
}
