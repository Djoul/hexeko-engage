<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\AmilonDebugCommand;
use App\Integrations\Vouchers\Amilon\DTO\ContractDTO;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonContractService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
class AmilonDebugCommandTest extends TestCase
{
    use DatabaseTransactions;

    private AmilonAuthService $authService;

    private AmilonContractService $contractService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->mock(AmilonAuthService::class);
        $this->contractService = $this->mock(AmilonContractService::class);
    }

    #[Test]
    public function it_successfully_runs_debug_command_when_all_services_work(): void
    {
        // Mock configuration
        config([
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-secret',
            'services.amilon.username' => 'test-user',
            'services.amilon.password' => 'test-pass',
            'services.amilon.token_url' => 'https://test.amilon.eu/token',
            'services.amilon.api_url' => 'https://test.amilon.eu/api',
            'services.amilon.contrat_id' => 'test-contract-123',
        ]);

        // Mock authentication
        $this->authService
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIn0.test');

        // Mock contract retrieval
        $contractDTO = ContractDTO::fromArray([
            'contractId' => 'test-contract-123',
            'contractName' => 'Test Contract',
            'startDate' => '2025-01-01T00:00:00',
            'endDate' => '2025-12-31T00:00:00',
            'currentAmount' => 1000.0,
            'previousAmount' => 0.0,
            'lastUpdate' => '2025-01-01T00:00:00',
            'currencyIsoCode' => 'EUR',
        ]);

        $this->contractService
            ->shouldReceive('getContract')
            ->with('test-contract-123')
            ->once()
            ->andReturn($contractDTO);

        // Mock health checks
        $this->authService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        $this->contractService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        // Execute command
        $this->artisan(AmilonDebugCommand::class)
            ->expectsOutput('=== Amilon API Debug Tool ===')
            ->expectsOutput('✅ Configuration is valid')
            ->expectsOutput('✅ Authentication successful')
            ->expectsOutput('✅ Contract retrieved successfully')
            ->expectsOutput('✅ Service is healthy')
            ->expectsOutput('✅ All checks passed successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_when_configuration_is_missing(): void
    {
        // Clear configuration
        config([
            'services.amilon.client_id' => null,
            'services.amilon.client_secret' => null,
            'services.amilon.username' => null,
            'services.amilon.password' => null,
        ]);

        // Execute command
        $this->artisan(AmilonDebugCommand::class)
            ->expectsOutput('❌ Configuration check failed. Please verify your .env file.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_authentication_fails(): void
    {
        // Mock configuration
        config([
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-secret',
            'services.amilon.username' => 'test-user',
            'services.amilon.password' => 'test-pass',
            'services.amilon.token_url' => 'https://test.amilon.eu/token',
            'services.amilon.api_url' => 'https://test.amilon.eu/api',
            'services.amilon.contrat_id' => 'test-contract-123',
        ]);

        // Mock authentication failure
        $this->authService
            ->shouldReceive('getAccessToken')
            ->once()
            ->andThrow(new Exception('Authentication failed'));

        // Execute command
        $this->artisan(AmilonDebugCommand::class)
            ->expectsOutput('❌ Authentication failed. Check logs for details.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_contract_retrieval_fails(): void
    {
        // Mock configuration
        config([
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-secret',
            'services.amilon.username' => 'test-user',
            'services.amilon.password' => 'test-pass',
            'services.amilon.token_url' => 'https://test.amilon.eu/token',
            'services.amilon.api_url' => 'https://test.amilon.eu/api',
            'services.amilon.contrat_id' => 'test-contract-123',
        ]);

        // Mock authentication success
        $this->authService
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('test-token');

        // Mock contract retrieval failure
        $this->contractService
            ->shouldReceive('getContract')
            ->with('test-contract-123')
            ->once()
            ->andThrow(new Exception('Contract not found'));

        // Execute command
        $this->artisan(AmilonDebugCommand::class)
            ->expectsOutput('❌ Contract retrieval failed. Check logs for details.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_shows_detailed_information_with_show_details_option(): void
    {
        // Mock configuration
        config([
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-secret',
            'services.amilon.username' => 'test-user',
            'services.amilon.password' => 'test-pass',
            'services.amilon.token_url' => 'https://test.amilon.eu/token',
            'services.amilon.api_url' => 'https://test.amilon.eu/api',
            'services.amilon.contrat_id' => 'test-contract-123',
        ]);

        // Mock authentication
        $this->authService
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIn0.test');

        // Mock contract retrieval
        $contractDTO = ContractDTO::fromArray([
            'contractId' => 'test-contract-123',
            'contractName' => 'Test Contract',
            'startDate' => '2025-01-01T00:00:00',
            'endDate' => '2025-12-31T00:00:00',
            'currentAmount' => 1000.0,
            'previousAmount' => 0.0,
            'lastUpdate' => '2025-01-01T00:00:00',
            'currencyIsoCode' => 'EUR',
        ]);

        $this->contractService
            ->shouldReceive('getContract')
            ->with('test-contract-123')
            ->once()
            ->andReturn($contractDTO);

        // Mock health checks
        $this->authService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        $this->contractService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        // Execute command with --show-details
        $result = $this->artisan(AmilonDebugCommand::class, ['--show-details' => true]);

        // Check that detailed information is shown
        $result->expectsOutput('  ✓ Client ID: test-client-id')
            ->expectsOutput('✅ All checks passed successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_accepts_custom_contract_id(): void
    {
        // Mock configuration
        config([
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-secret',
            'services.amilon.username' => 'test-user',
            'services.amilon.password' => 'test-pass',
            'services.amilon.token_url' => 'https://test.amilon.eu/token',
            'services.amilon.api_url' => 'https://test.amilon.eu/api',
            'services.amilon.contrat_id' => 'default-contract-123',
        ]);

        $customContractId = 'custom-contract-456';

        // Mock authentication
        $this->authService
            ->shouldReceive('getAccessToken')
            ->once()
            ->andReturn('test-token');

        // Mock contract retrieval with custom ID
        $contractDTO = ContractDTO::fromArray([
            'contractId' => $customContractId,
            'contractName' => 'Custom Contract',
            'startDate' => '2025-01-01T00:00:00',
            'endDate' => '2025-12-31T00:00:00',
            'currentAmount' => 2000.0,
            'previousAmount' => 0.0,
            'lastUpdate' => '2025-01-01T00:00:00',
            'currencyIsoCode' => 'EUR',
        ]);

        $this->contractService
            ->shouldReceive('getContract')
            ->with($customContractId)
            ->once()
            ->andReturn($contractDTO);

        // Mock health checks
        $this->authService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        $this->contractService
            ->shouldReceive('isHealthy')
            ->once()
            ->andReturn(true);

        // Execute command with custom contract ID
        $this->artisan(AmilonDebugCommand::class, ['--contract-id' => $customContractId])
            ->expectsOutput('✅ All checks passed successfully!')
            ->assertExitCode(0);
    }
}
