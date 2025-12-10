<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\DTO\ContractDTO;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonContractService;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonContractServiceTest extends ProtectedRouteTestCase
{
    private AmilonContractService $contractService;

    protected string $baseUrl;

    protected string $contractId;

    private $mockAuthService;

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {

        parent::setUp();

        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', 'test-contract-id');

        $this->baseUrl = config('services.amilon.api_url').'/b2bwebapi/v1';
        $this->contractId = config('services.amilon.contrat_id');

        // Create a mock for the auth service
        $this->mockAuthService = Mockery::mock(AmilonAuthService::class);

        $this->mockAuthService->shouldReceive('getAccessToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        $this->mockAuthService->shouldReceive('refreshToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        // Create the service with the mock auth service
        $this->contractService = new AmilonContractService($this->mockAuthService);
    }

    #[Test]
    public function test_get_contract_returns_valid_contract_data(): void
    {
        // Mock HTTP response from Amilon API
        $uri = "{$this->baseUrl}/contracts/{$this->contractId}";

        Http::fake([
            $uri => Http::response([
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

        // Call the service method
        $contract = $this->contractService->getContract($this->contractId);

        // Assert the response structure
        $this->assertInstanceOf(ContractDTO::class, $contract);

        // Check specific values
        $this->assertEquals('Test Contract', $contract->contractName);
        $this->assertEquals($this->contractId, $contract->contractId);
        $this->assertEquals('2023-01-01T00:00:00', $contract->startDate);
        $this->assertEquals('2023-12-31T00:00:00', $contract->endDate);
        $this->assertEquals(5000.75, $contract->currentAmount);
        $this->assertEquals(6000.50, $contract->previousAmount);
        $this->assertEquals('2023-06-15T12:30:45', $contract->lastUpdate);
        $this->assertEquals('EUR', $contract->currencyIsoCode);

        // Verify that the API was called with the token
        Http::assertSent(function ($request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });

        // Verify that the API was called only once
        Http::assertSentCount(1);
    }

    #[Test]
    public function test_get_contract_handles_api_error(): void
    {
        // Mock HTTP response for API error
        $uri = "{$this->baseUrl}/contracts/{$this->contractId}";
        Http::fake([
            $uri => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        // Expect an exception
        $this->expectException(Exception::class);

        // Call the service method
        $this->contractService->getContract($this->contractId);

        // Verify that the API was called with the token
        Http::assertSent(function ($request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_auth_error_refreshes_token_and_retries(): void
    {
        // Create a new mock for this specific test
        $testMockAuthService = Mockery::mock(AmilonAuthService::class);
        $testMockAuthService->shouldReceive('getAccessToken')
            ->andReturn($this->mockToken);
        $testMockAuthService->shouldReceive('refreshToken')
            ->once()
            ->andReturn('new-token-456');

        // Replace the service with one using our test-specific mock
        $this->contractService = new AmilonContractService($testMockAuthService);

        // Mock HTTP responses - first 401, then 200 after token refresh
        $uri = "{$this->baseUrl}/contracts/{$this->contractId}";
        Http::fake([
            // First request fails with 401
            $uri => Http::sequence()
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

        // Call the service method
        $contract = $this->contractService->getContract($this->contractId);

        // Assert the response structure
        $this->assertInstanceOf(ContractDTO::class, $contract);

        // Check specific values
        $this->assertEquals('Test Contract', $contract->contractName);
        $this->assertEquals($this->contractId, $contract->contractId);

        // Verify that the API was called twice (once with old token, once with new token)
        Http::assertSentCount(2);

        // Verify the first call had the original token
        Http::assertSent(function ($request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });

        // Verify the second call had the new token
        Http::assertSent(function ($request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer new-token-456');
        });
    }
}
