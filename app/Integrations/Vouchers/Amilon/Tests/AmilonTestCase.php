<?php

namespace App\Integrations\Vouchers\Amilon\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Base test case for Amilon integration tests.
 *
 * This class provides common setup and utilities specific to testing
 * the Amilon voucher integration without extending ProtectedRouteTestCase.
 */
abstract class AmilonTestCase extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected string $mockToken = 'mock-token-123';

    protected string $baseUrl;

    protected string $contractId;

    protected string $tokenUrl;

    final protected function setUp(): void
    {
        parent::setUp();

        // Configure Amilon service settings
        $this->setupAmilonConfig();

        // Clear HTTP mocks
        Http::clearResolvedInstances();

        // Clean Amilon tables in correct order for foreign keys
        $this->cleanAmilonTables();

    }

    /**
     * Set up Amilon configuration for testing.
     */
    final protected function setupAmilonConfig(): void
    {
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', '123-456-789');
        Config::set('services.amilon.token_url', 'https://b2bsales-sso.amilon.eu/connect/token');
        Config::set('services.amilon.client_id', 'test-client-id');
        Config::set('services.amilon.client_secret', 'test-client-secret');
        Config::set('services.amilon.username', 'test-username');
        Config::set('services.amilon.password', 'test-password');

        $baseUrlConfig = config('services.amilon.api_url');
        $this->baseUrl = is_string($baseUrlConfig) ? $baseUrlConfig : '';

        $contractIdConfig = config('services.amilon.contrat_id');
        $this->contractId = is_string($contractIdConfig) ? $contractIdConfig : '';

        $tokenUrlConfig = config('services.amilon.token_url');
        $this->tokenUrl = is_string($tokenUrlConfig) ? $tokenUrlConfig : '';
    }

    /**
     * Clean Amilon database tables in correct order to respect foreign key constraints.
     */
    final protected function cleanAmilonTables(): void
    {
        // Delete in correct order to avoid foreign key violations
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_merchants')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();
    }

    /**
     * Mock standard Amilon API responses for token and merchants.
     *
     * @param  array<array<string, mixed>>  $merchants
     */
    final protected function mockStandardAmilonResponses(array $merchants = []): void
    {
        if ($merchants === []) {
            $merchants = $this->getDefaultMerchants();
        }

        Http::fake([
            $this->tokenUrl => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/*/retailers' => Http::response($merchants, 200),
            'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/*/*/retailers' => Http::response($merchants, 200),
        ]);
    }

    /**
     * Get default merchant data for testing.
     *
     * @return array<array<string, mixed>>
     */
    final protected function getDefaultMerchants(): array
    {
        return [
            [
                'Name' => 'Fnac',
                'CountryISOAlpha3' => 'ITA',
                'category' => 'Electronics',
                'RetailerId' => 'FNAC001',
                'LongDescription' => 'Fnac gift card',
                'ImageUrl' => 'https://example.com/fnac.jpg',
            ],
            [
                'Name' => 'Decathlon',
                'CountryISOAlpha3' => 'ITA',
                'category' => 'Sports',
                'RetailerId' => 'DECA001',
                'LongDescription' => 'Decathlon gift card',
                'ImageUrl' => 'https://example.com/decathlon.jpg',
            ],
        ];
    }

    /**
     * Get the Amilon merchants API URL.
     */
    final protected function getMerchantsApiUrl(string $culture = 'pt-PT'): string
    {
        return "b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/{$this->contractId}/{$culture}/retailers";
    }

    /**
     * Run Amilon migrations if needed.
     */
    final protected function runAmilonMigrations(): void
    {
        $this->artisan('migrate', [
            '--path' => 'app/Modules/Vouchers/Amilon/Database/migrations',
            '--realpath' => false,
        ]);
    }
}
