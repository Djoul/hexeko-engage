<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Vouchers\Amilon\DTO\ContractDTO;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmilonContractService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The base URL for the Amilon API.
     */
    protected string $baseUrl;

    protected AmilonAuthService $authService;

    /**
     * Create a new AmilonContractService instance.
     */
    public function __construct(AmilonAuthService $authService)
    {
        $this->authService = $authService;
        $apiUrl = config('services.amilon.api_url');
        $this->baseUrl = (is_string($apiUrl) ? $apiUrl : '').'/b2bwebapi/v1';
    }

    /**
     * Get contract information from Amilon API.
     *
     * @param  string  $contractId  The ID of the contract
     * @return ContractDTO The contract information
     *
     * @throws Exception If the API request fails
     */
    public function getContract(string $contractId): ContractDTO
    {
        try {
            $token = $this->authService->getAccessToken();

            $url = "{$this->baseUrl}/contracts/{$contractId}";
            $response = Http::withToken($token)
                ->get($url);

            // Log automatique de l'appel API
            $this->logApiCall(
                'GET',
                "/contracts/{$contractId}",
                $response->status(),
                $response->json()
            );

            if ($response->successful()) {
                $contractData = $response->json();

                return ContractDTO::fromArray(is_array($contractData) ? $contractData : []);
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                $response = Http::withToken($token)
                    ->timeout(5)
                    ->get($url);

                // Log automatique du retry
                $this->logApiCall(
                    'GET',
                    "/contracts/{$contractId} (retry)",
                    $response->status(),
                    $response->json()
                );

                if ($response->successful()) {
                    $contractData = $response->json();

                    return ContractDTO::fromArray(is_array($contractData) ? $contractData : []);
                }
            }

            // If API request failed, log the error and throw an exception
            Log::error('Failed to fetch contract from Amilon API', [
                'status' => $response->status(),
                'body' => $response->body(),
                'contract_id' => $contractId,
            ]);

            throw new Exception('Failed to fetch contract from Amilon API: '.$response->body());
        } catch (Exception $e) {
            // Log any exceptions and rethrow
            Log::error('Exception while fetching contract from Amilon API', [
                'exception' => $e,
                'contract_id' => $contractId,
            ]);

            throw $e;
        }
    }

    /**
     * Get the provider name for logging.
     */
    public function getProviderName(): string
    {
        return 'amilon';
    }

    /**
     * Get the API version.
     */
    public function getApiVersion(): string
    {
        return 'v1';
    }

    /**
     * Check if the service is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            $token = $this->authService->getAccessToken();

            return ! empty($token);
        } catch (Exception $e) {
            return false;
        }
    }
}
