<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WellWoApiService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    private string $baseUrl;

    private int $timeout;

    /** @phpstan-ignore-next-line */
    private int $retryTimes;

    /** @phpstan-ignore-next-line */
    private int $retryDelay;

    public function __construct()
    {
        $baseUrlConfig = Config::get('services.wellwo.api_url', 'https://my.wellwo.net/api/v1/');
        $this->baseUrl = is_string($baseUrlConfig) ? $baseUrlConfig : 'https://my.wellwo.net/api/v1/';

        $timeoutConfig = Config::get('services.wellwo.timeout', 30);
        $this->timeout = is_numeric($timeoutConfig) ? (int) $timeoutConfig : 30;

        $retryConfig = Config::get('services.wellwo.retry_times', 3);
        $this->retryTimes = is_numeric($retryConfig) ? (int) $retryConfig : 3;

        $delayConfig = Config::get('services.wellwo.retry_delay', 100);
        $this->retryDelay = is_numeric($delayConfig) ? (int) $delayConfig : 100;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(array $data = []): array
    {
        return $this->request($this->baseUrl, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $url, array $data = []): array
    {
        // Add authToken to the data
        $requestData = array_merge($data, [
            'authToken' => config('services.wellwo.auth_token'),
        ]);

        // Force POST to the final URL with trailing slash to avoid redirects
        $finalUrl = rtrim($url, '/').'/';

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($finalUrl, $requestData);

            // Get the response body and remove BOM if present
            $responseBody = $response->body();
            $responseBody = preg_replace('/^\xEF\xBB\xBF/', '', $responseBody);

            // Manually decode JSON after BOM removal
            $responseData = $responseBody !== null ? json_decode($responseBody, true) : null;

            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('WellWo API JSON decode error', [
                    'error' => json_last_error_msg(),
                    'body' => $responseBody,
                ]);

                // Log the failed API call before throwing exception
                $endpoint = array_key_exists('op', $requestData) && is_string($requestData['op']) ? $requestData['op'] : 'unknown';
                $this->logApiCall(
                    'POST',
                    $endpoint,
                    $response->status(),
                    [],
                    null
                );

                throw WellWoApiException::apiError(
                    'Invalid JSON response: '.json_last_error_msg(),
                    $response->status()
                );
            }

            // Log successful API call
            $endpoint = array_key_exists('op', $requestData) && is_string($requestData['op']) ? $requestData['op'] : 'unknown';
            $this->logApiCall(
                'POST',
                $endpoint,
                $response->status(),
                is_array($responseData) ? $responseData : [],
                is_array($responseData) && array_key_exists('mediaItems', $responseData) ? 'mediaItems' : null
            );

            if (! $response->successful()) {
                $errorMessage = is_array($responseData) && array_key_exists('error', $responseData) && is_string($responseData['error'])
                    ? $responseData['error']
                    : 'Unknown error';
                throw WellWoApiException::apiError($errorMessage, $response->status());
            }

            return is_array($responseData) ? $responseData : [];
        } catch (WellWoApiException $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (RequestException $e) {
            $response = $e->response;
            $statusCode = $response->status();
            $body = $response->body();

            // Try to decode after removing BOM
            $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
            $decodedBody = $body !== null ? json_decode($body, true) : null;
            $responseData = is_array($decodedBody) ? $decodedBody : ['error' => $body];

            Log::error('WellWo API error', [
                'url' => $finalUrl,
                'status' => $statusCode,
                'response' => $responseData,
            ]);

            $errorMessage = is_array($responseData) && array_key_exists('error', $responseData) && is_string($responseData['error'])
                ? $responseData['error']
                : 'Unknown error';
            throw WellWoApiException::apiError($errorMessage, $statusCode);
        } catch (Exception $e) {
            Log::error('WellWo API connection error', [
                'url' => $finalUrl,
                'error' => $e->getMessage(),
            ]);

            throw WellWoApiException::connectionFailed($finalUrl, $e);
        }
    }

    public function getProviderName(): string
    {
        return 'wellwo';
    }

    public function getApiVersion(): string
    {
        return 'v1';
    }

    public function isHealthy(): bool
    {
        try {
            // Check if auth token is configured
            $token = config('services.wellwo.auth_token');

            return ! empty($token);
        } catch (Exception) {
            return false;
        }
    }
}
