<?php

namespace Tests\Feature\Http\Controllers\V1\User\MeController;

use App\Enums\CreditTypes;
use App\Models\CreditBalance;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('me')]
#[Group('user')]
class MeControllerTest extends ProtectedRouteTestCase
{
    const ME_ENDPOINT = '/api/v1/me';

    #[Test]
    public function me_endpoint_returns_authenticated_user_data(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        // Assert response structure and data
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'enabled',
                    'credit_balance',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    #[Test]
    public function me_endpoint_returns_correct_credit_balance_structure(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create different types of credit balances
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => CreditTypes::CASH,
            'balance' => 10000,
            'context' => ['currency' => 'EUR'],
        ]);

        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => CreditTypes::AI_TOKEN,
            'balance' => 500,
            'context' => ['model' => 'gpt-4'],
        ]);

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        // Assert response
        $response->assertStatus(200);

        $creditBalance = $response->json('data.credit_balance');

        // Verify credit balance structure is grouped by type
        $this->assertIsArray($creditBalance);
        $this->assertArrayHasKey(CreditTypes::CASH, $creditBalance);
        $this->assertArrayHasKey(CreditTypes::AI_TOKEN, $creditBalance);
        // Verify each credit type contains expected data
        $this->assertCount(4, $creditBalance);
        $this->assertEquals(10000, $creditBalance[CreditTypes::CASH]);
        $this->assertEquals(500, $creditBalance[CreditTypes::AI_TOKEN]);
    }

    #[Test]
    public function me_endpoint_returns_empty_credit_balance_when_no_credits(): void
    {
        // Create a user without any credits
        $user = User::factory()->create();

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        // Assert response
        $response->assertStatus(200);

        $creditBalance = $response->json('data.credit_balance');

        // Verify credit balance is empty
        $this->assertIsArray($creditBalance);

        $this->assertArrayHasKey(CreditTypes::CASH, $creditBalance);
        $this->assertEquals(0, $creditBalance[CreditTypes::CASH]);

        $this->assertArrayHasKey(CreditTypes::AI_TOKEN, $creditBalance);
        $this->assertEquals(0, $creditBalance[CreditTypes::AI_TOKEN]);

        $this->assertArrayHasKey(CreditTypes::SMS, $creditBalance);
        $this->assertEquals(0, $creditBalance[CreditTypes::SMS]);

        $this->assertArrayHasKey(CreditTypes::EMAIL, $creditBalance);
        $this->assertEquals(0, $creditBalance[CreditTypes::EMAIL]);
    }

    #[Test]
    public function me_endpoint_requires_authentication(): void
    {
        // Make request to the me endpoint without authentication
        $response = $this->getJson(self::ME_ENDPOINT);

        // Assert response is unauthorized
        $response->assertStatus(401);
    }
}
