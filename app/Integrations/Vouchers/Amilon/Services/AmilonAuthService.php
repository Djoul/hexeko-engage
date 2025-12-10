<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmilonAuthService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The cache key for storing the access token.
     */
    protected string $cacheKey = 'amilon_token';

    /**
     * The cache TTL in seconds (4 minutes and 10 seconds).
     * Token is valid for 5 minutes, but we use a shorter TTL to avoid expiration issues.
     */
    protected int $cacheTtl = 250;

    /**
     * Get the access token for Amilon API.
     *
     * @return string The access token
     *
     * @throws Exception If authentication fails
     */
    public function getAccessToken(): string
    {
        try {
            $tokenUrl = config('services.amilon.token_url');
            if (! is_string($tokenUrl)) {
                throw new Exception('Token URL not configured properly');
            }

            // Get credentials from config
            $clientId = config('services.amilon.client_id');
            $clientSecret = config('services.amilon.client_secret');
            $username = config('services.amilon.username');
            $password = config('services.amilon.password');

            // temp prevent bad escaping of \
            $password = str_replace('\$#', '$#', $password);

            // DEBUG MODE: Logging all credentials in plain text for troubleshooting
            // TODO: REMOVE THIS BEFORE PRODUCTION DEPLOYMENT
            Log::warning('Amilon authentication attempt - DEBUG MODE', [
                'token_url' => $tokenUrl,
                'client_id' => $clientId,
                'client_id_length' => is_string($clientId) ? strlen($clientId) : 0,
                'client_secret' => $clientSecret,
                'client_secret_length' => is_string($clientSecret) ? strlen($clientSecret) : 0,
                'username' => $username,
                'username_length' => is_string($username) ? strlen($username) : 0,
                'password' => $password,
                'password_length' => is_string($password) ? strlen($password) : 0,
                'has_whitespace_username' => is_string($username) && (trim($username) !== $username),
                'has_whitespace_password' => is_string($password) && (trim($password) !== $password),
                'environment' => app()->environment(),
            ]);

            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'password',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password,
            ]);
            // Log automatique de l'appel API
            $pathValue = parse_url($tokenUrl, PHP_URL_PATH);
            $path = is_string($pathValue) ? $pathValue : '/oauth/token';

            $responseData = $response->json();
            $logData = is_array($responseData) ? $responseData : ['body' => substr($response->body(), 0, 500)];

            $this->logApiCall(
                'POST',
                $path,
                $response->status(),
                $logData
            );

            if (! $response->successful()) {
                $responseBody = $response->body();
                $responseJson = $response->json();

                Log::error('Failed to authenticate with Amilon - DEBUG MODE', [
                    'status' => $response->status(),
                    'body' => $responseBody,
                    'error' => is_array($responseJson) ? ($responseJson['error'] ?? null) : null,
                    'error_description' => is_array($responseJson) ? ($responseJson['error_description'] ?? null) : null,
                    'headers' => [
                        'content-type' => $response->header('Content-Type'),
                        'content-length' => $response->header('Content-Length'),
                    ],
                    'request_headers' => [
                        'content-type' => 'application/x-www-form-urlencoded',
                    ],
                    'request_payload' => [
                        'grant_type' => 'password',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'username' => $username,
                        'password' => $password,
                    ],
                ]);
                throw new Exception('Failed to authenticate with Amilon', $response->status());
            }

            $accessToken = $response->json('access_token');
            if (! is_string($accessToken)) {
                throw new Exception('Invalid access token received from Amilon');
            }

            return $accessToken;
        } catch (Exception $e) {
            Log::error('Exception while authenticating with Amilon', [
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refresh the access token by clearing the cache and getting a new one.
     *
     * @return string The new access token
     *
     * @throws Exception If authentication fails
     */
    public function refreshToken(): string
    {
        return $this->getAccessToken();
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
            $this->getAccessToken();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
