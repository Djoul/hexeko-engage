<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
#[Group('amilon-contract')]
class FetchContractTest extends ProtectedRouteTestCase
{
    const CONTRACT_URL = '/api/v1/vouchers/amilon/contract-info';

    const GET_CONTRACT_AMILON_URL = 'b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/';

    private string $mockToken = 'mock-token-123';

    private string $contractId;

    #[Test]
    public function test_contract_endpoint_returns_valid_json_structure(): void
    {
        // Mock HTTP response from Amilon API
        Http::fake([
            self::GET_CONTRACT_AMILON_URL.$this->contractId => Http::response([
                'contractName' => 'Test Contract',
                'ContractId' => $this->contractId,
                'StartDate' => '2023-01-01T00:00:00',
                'EndDate' => '2023-12-31T00:00:00',
                'CurrentAmount' => 5000.75,
                'PreviousAmount' => 6000.50,
                'LastUpdate' => '2023-06-15T12:30:45',
                'CurrencyIsoCode' => 'EUR',
            ], 200),
        ]);

        // Create a user
        $user = User::factory()->create();

        // Make request to the contract endpoint
        $response = $this->actingAs($user)
            ->getJson(self::CONTRACT_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'contractName',
                'contractId',
                'startDate',
                'endDate',
                'currentAmount',
                'previousAmount',
                'lastUpdate',
                'currencyIsoCode',
            ]);

        // Verify that the API was called with the token
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://'.self::GET_CONTRACT_AMILON_URL.$this->contractId &&
                $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_contract_endpoint_handles_api_error(): void
    {
        // Mock HTTP response for API error
        Http::fake([
            self::GET_CONTRACT_AMILON_URL.$this->contractId => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        // Create a user
        $user = User::factory()->create();

        // Make request to the contract endpoint
        $response = $this->actingAs($user)
            ->getJson(self::CONTRACT_URL);

        // Assert response
        $response->assertStatus(500)
            ->assertJsonStructure([
                'error',
                'message',
            ]);

        // Verify that the API was called with the token
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://'.self::GET_CONTRACT_AMILON_URL.$this->contractId &&
                $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_contract_endpoint_handles_auth_error(): void
    {
        // Mock HTTP responses - first 401, then 200 after token refresh
        Http::fake([
            // First request fails with 401
            self::GET_CONTRACT_AMILON_URL.$this->contractId => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push([
                    'contractName' => 'Test Contract',
                    'ContractId' => $this->contractId,
                    'StartDate' => '2023-01-01T00:00:00',
                    'EndDate' => '2023-12-31T00:00:00',
                    'CurrentAmount' => 5000.75,
                    'PreviousAmount' => 6000.50,
                    'LastUpdate' => '2023-06-15T12:30:45',
                    'CurrencyIsoCode' => 'EUR',
                ], 200),
        ]);

        // Create a user
        $user = User::factory()->create();

        // Make request to the contract endpoint
        $response = $this->actingAs($user)
            ->getJson(self::CONTRACT_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'contractName',
                'contractId',
                'startDate',
                'endDate',
                'currentAmount',
                'previousAmount',
                'lastUpdate',
                'currencyIsoCode',
            ]);

        // Verify that the API was called twice (once with old token, once with new token)
        Http::assertSentCount(2);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', 'test-contract-id');

        $this->contractId = config('services.amilon.contrat_id');

        // Mock the auth service
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn($this->mockToken);

            $mock->shouldReceive('refreshToken')
                ->andReturn('new-token-456');
        });
    }
}
