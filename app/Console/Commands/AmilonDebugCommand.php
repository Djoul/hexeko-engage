<?php

namespace App\Console\Commands;

use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonContractService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AmilonDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'amilon:debug
                            {--contract-id= : Specific contract ID to test (optional)}
                            {--no-retry : Skip retry logic on 401 errors}
                            {--show-details : Show detailed HTTP responses and token information}';

    /**
     * The console command description.
     */
    protected $description = 'Debug Amilon API authentication and contract retrieval';

    /**
     * Execute the console command.
     */
    public function handle(AmilonAuthService $authService, AmilonContractService $contractService): int
    {
        $this->info('=== Amilon API Debug Tool ===');
        $this->newLine();

        // Step 1: Verify configuration
        $this->line('📋 Step 1: Configuration Check');
        $this->line('─────────────────────────────────────');
        $configStatus = $this->checkConfiguration();

        if (! $configStatus) {
            $this->error('❌ Configuration check failed. Please verify your .env file.');

            return self::FAILURE;
        }

        $this->info('✅ Configuration is valid');
        $this->newLine();

        // Step 2: Test authentication
        $this->line('🔐 Step 2: Authentication Test');
        $this->line('─────────────────────────────────────');
        $token = $this->testAuthentication($authService);

        if (in_array($token, [null, '', '0'], true)) {
            $this->error('❌ Authentication failed. Check logs for details.');

            return self::FAILURE;
        }

        $this->info('✅ Authentication successful');
        $this->line('Token (first 20 chars): '.substr($token, 0, 20).'...');
        $this->newLine();

        // Step 3: Test contract retrieval
        $this->line('📄 Step 3: Contract Retrieval Test');
        $this->line('─────────────────────────────────────');
        $contractId = $this->option('contract-id') ?: (string) config('services.amilon.contrat_id');
        $contractStatus = $this->testContractRetrieval($contractService, $contractId);

        if (! $contractStatus) {
            $this->error('❌ Contract retrieval failed. Check logs for details.');

            return self::FAILURE;
        }

        $this->info('✅ Contract retrieved successfully');
        $this->newLine();

        // Step 4: Health check
        $this->line('🏥 Step 4: Service Health Check');
        $this->line('─────────────────────────────────────');
        $healthStatus = $this->testHealthCheck($authService, $contractService);

        if (! $healthStatus) {
            $this->warn('⚠️  Service health check returned false');

            return self::FAILURE;
        }

        $this->info('✅ Service is healthy');
        $this->newLine();

        // Summary
        $this->info('═══════════════════════════════════════');
        $this->info('✅ All checks passed successfully!');
        $this->info('═══════════════════════════════════════');

        return self::SUCCESS;
    }

    /**
     * Check if all required configuration values are present.
     */
    protected function checkConfiguration(): bool
    {
        $requiredConfigs = [
            'services.amilon.client_id' => 'Client ID',
            'services.amilon.client_secret' => 'Client Secret',
            'services.amilon.username' => 'Username',
            'services.amilon.password' => 'Password',
            'services.amilon.token_url' => 'Token URL',
            'services.amilon.api_url' => 'API URL',
            'services.amilon.contrat_id' => 'Contract ID',
        ];

        $allValid = true;

        foreach ($requiredConfigs as $key => $label) {
            $value = config($key);
            $isSet = ! empty($value);

            if ($isSet) {
                $this->line("  ✓ {$label}: ".($this->option('show-details') ? $value : '[SET]'));
            } else {
                $this->error("  ✗ {$label}: [NOT SET]");
                $allValid = false;
            }
        }

        return $allValid;
    }

    /**
     * Test authentication with Amilon API.
     */
    protected function testAuthentication(AmilonAuthService $authService): ?string
    {
        try {
            $this->line('  → Requesting access token...');

            // Display config info before attempting (masked for security)
            if ($this->option('show-details')) {
                $clientId = config('services.amilon.client_id');
                $username = config('services.amilon.username');
                $password = config('services.amilon.password');

                $this->line('  Configuration check:');
                $this->line('    - Client ID length: '.(is_string($clientId) ? strlen($clientId) : 0));
                $this->line('    - Client ID preview: '.(is_string($clientId) && strlen($clientId) > 4 ? substr($clientId, 0, 4).'***' : '[NOT SET]'));
                $this->line('    - Username length: '.(is_string($username) ? strlen($username) : 0));
                $this->line('    - Username preview: '.(is_string($username) && strlen($username) > 3 ? substr($username, 0, 3).'***' : '[NOT SET]'));
                $this->line('    - Password length: '.(is_string($password) ? strlen($password) : 0));
                $this->line('    - Whitespace in username: '.(is_string($username) && (trim($username) !== $username) ? 'YES ⚠️' : 'NO'));
                $this->line('    - Whitespace in password: '.(is_string($password) && (trim($password) !== $password) ? 'YES ⚠️' : 'NO'));
                $this->newLine();
            }

            $startTime = microtime(true);
            $token = $authService->getAccessToken();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->line("  ✓ Token received in {$duration}ms");

            if ($this->option('show-details')) {
                $this->line('  Token length: '.strlen($token).' characters');
                $this->line('  Token preview: '.substr($token, 0, 50).'...');
            }

            // Test token validity by decoding if it's a JWT
            if ($this->isJwt($token)) {
                $this->line('  ✓ Token format: JWT');
                $payload = $this->decodeJwt($token);

                if ($payload && $this->option('show-details')) {
                    $this->line('  Token claims:');
                    foreach ($payload as $key => $value) {
                        if (is_scalar($value)) {
                            $this->line("    - {$key}: {$value}");
                        }
                    }
                }
            } else {
                $this->line('  ℹ Token format: Bearer token (not JWT)');
            }

            return $token;
        } catch (Exception $e) {
            $this->error('  ✗ Authentication failed');
            $this->error("  Error: {$e->getMessage()}");

            if ($this->option('show-details')) {
                $this->line('  Stack trace:');
                $this->line($e->getTraceAsString());
            }

            Log::error('Amilon debug authentication failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Test contract retrieval.
     */
    protected function testContractRetrieval(AmilonContractService $contractService, string $contractId): bool
    {
        try {
            $this->line("  → Fetching contract: {$contractId}");

            $startTime = microtime(true);
            $contract = $contractService->getContract($contractId);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->line("  ✓ Contract retrieved in {$duration}ms");

            if ($this->option('show-details')) {
                $contractArray = $contract->toArray();
                $this->line('  Contract details:');
                foreach ($contractArray as $key => $value) {
                    if (is_scalar($value)) {
                        $this->line("    - {$key}: {$value}");
                    } elseif (is_array($value)) {
                        $this->line("    - {$key}: [".count($value).' items]');
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->error('  ✗ Contract retrieval failed');
            $this->error("  Error: {$e->getMessage()}");

            if ($this->option('show-details')) {
                $this->line('  Stack trace:');
                $this->line($e->getTraceAsString());
            }

            Log::error('Amilon debug contract retrieval failed', [
                'exception' => $e->getMessage(),
                'contract_id' => $contractId,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Test service health checks.
     */
    protected function testHealthCheck(AmilonAuthService $authService, AmilonContractService $contractService): bool
    {
        try {
            $this->line('  → Testing auth service health...');
            $authHealth = $authService->isHealthy();
            $this->line('  Auth service: '.($authHealth ? '✓ Healthy' : '✗ Unhealthy'));

            $this->line('  → Testing contract service health...');
            $contractHealth = $contractService->isHealthy();
            $this->line('  Contract service: '.($contractHealth ? '✓ Healthy' : '✗ Unhealthy'));

            return $authHealth && $contractHealth;
        } catch (Exception $e) {
            $this->error('  ✗ Health check failed');
            $this->error("  Error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if a token is a JWT.
     */
    protected function isJwt(string $token): bool
    {
        return substr_count($token, '.') === 2;
    }

    /**
     * Decode a JWT token (without signature verification).
     */
    protected function decodeJwt(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = base64_decode(strtr($parts[1], '-_', '+/'));
            if ($payload === '' || $payload === '0') {
                return null;
            }

            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
