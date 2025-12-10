<?php

namespace App\Services\Vault;

use App\DTOs\Vault\VaultSessionDTO;
use App\Exceptions\Vault\VaultException;
use App\Models\Financer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VaultService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $appId;

    protected ?string $serviceId = null;

    public function __construct()
    {
        $this->baseUrl = is_string(config('services.apideck.base_url'))
            ? config('services.apideck.base_url')
            : throw new InvalidArgumentException('Invalid base_url');

        $this->apiKey = is_string(config('services.apideck.key'))
            ? config('services.apideck.key')
            : throw new InvalidArgumentException('Invalid apiKey');

        $this->appId = is_string(config('services.apideck.app_id'))
            ? config('services.apideck.app_id')
            : throw new InvalidArgumentException('Invalid appId');
    }

    /**
     * @param  array<string, mixed>  $settings
     *
     * @throws VaultException
     */
    public function createSession(Financer $financer, string $consumerId, string $redirectUri, array $settings = []): VaultSessionDTO
    {
        if (empty($consumerId)) {
            throw new VaultException('Consumer ID is required');
        }

        // Extract service_id from settings if provided
        if (array_key_exists('service_id', $settings) && is_string($settings['service_id'])) {
            $this->serviceId = $settings['service_id'];
            Log::debug('VaultService: service_id extracted from settings', [
                'service_id' => $this->serviceId,
                'financer_id' => $financer->id,
            ]);
            // Remove service_id from settings as it's not part of Apideck Vault settings API
            unset($settings['service_id']);
        } else {
            Log::debug('VaultService: No service_id provided in settings', [
                'settings_keys' => array_keys($settings),
                'financer_id' => $financer->id,
            ]);
        }

        // Build payload for Apideck Vault API
        // API expects redirect_uri and settings at root level, NOT in a session wrapper
        $payload = [
            'redirect_uri' => $redirectUri,
        ];

        // Only add settings if not empty
        if ($settings !== []) {
            $payload['settings'] = $settings;
        }

        Log::debug('VaultService: Creating session', [
            'consumer_id' => $consumerId,
            'service_id' => $this->serviceId,
            'payload' => $payload,
        ]);

        return $this->makeApiRequest($consumerId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws VaultException
     */
    private function makeApiRequest(string $consumerId, array $payload): VaultSessionDTO
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $headers = [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'x-apideck-app-id' => $this->appId,
                    'x-apideck-consumer-id' => $consumerId,
                    'Content-Type' => 'application/json',
                ];

                // Add service_id header if specified
                if ($this->serviceId !== null) {
                    $headers['x-apideck-service-id'] = $this->serviceId;
                    Log::debug('VaultService: Added x-apideck-service-id header', [
                        'service_id' => $this->serviceId,
                        'headers' => array_keys($headers),
                    ]);
                } else {
                    Log::debug('VaultService: No service_id header added (will show all services)');
                }

                Log::debug('VaultService: Sending request to Apideck', [
                    'url' => "{$this->baseUrl}/vault/sessions",
                    'headers' => $headers,
                    'payload' => $payload,
                ]);

                $response = Http::withHeaders($headers)->timeout(30)->post("{$this->baseUrl}/vault/sessions", $payload);

                Log::debug('VaultService: Received response from Apideck', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! is_array($data) || ! array_key_exists('data', $data)) {
                        throw new VaultException('Invalid response from Apideck API');
                    }

                    if (! is_array($data['data'])) {
                        throw new VaultException('Invalid response from Apideck API');
                    }

                    // Ensure array keys are strings
                    /** @var array<string, mixed> $sessionData */
                    $sessionData = $data['data'];

                    return new VaultSessionDTO($sessionData);
                }

                $responseData = $response->json();
                /** @var array<string, mixed> $typedResponseData */
                $typedResponseData = is_array($responseData) ? $responseData : [];
                $this->handleApiError($response->status(), $typedResponseData);

            } catch (ConnectionException $e) {
                if ($attempt === $maxRetries - 1) {
                    throw new VaultException('Network error while connecting to Apideck');
                }
            } catch (VaultException $e) {
                // Retry only on 503 Service Unavailable
                if ($e->getCode() === 503 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    sleep(1); // Wait 1 second before retry

                    continue;
                }
                throw $e;
            }

            $attempt++;
        }

        throw new VaultException('Maximum retry attempts exceeded');
    }

    /**
     * @param  array<string, mixed>  $responseBody
     *
     * @throws VaultException
     */
    private function handleApiError(int $statusCode, array $responseBody): void
    {
        $errorMessage = 'Unknown error';
        if (array_key_exists('error', $responseBody) && is_array($responseBody['error']) && array_key_exists('message', $responseBody['error'])) {
            $errorMessage = is_string($responseBody['error']['message']) ? $responseBody['error']['message'] : 'Unknown error';
        }
        if ($statusCode === 401) {
            throw new VaultException('Apideck API authentication failed', 401);
        }
        if ($statusCode === 404) {
            throw new VaultException('Consumer not found in Apideck', 404);
        }

        if ($statusCode === 503) {
            throw new VaultException('Service unavailable', 503);
        }
        throw new VaultException("Apideck API Error: {$errorMessage}", $statusCode);
    }
}
