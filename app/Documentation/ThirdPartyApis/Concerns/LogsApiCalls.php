<?php

declare(strict_types=1);

namespace App\Documentation\ThirdPartyApis\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait LogsApiCalls
{
    /**
     * @param  array<string, mixed>  $response
     */
    protected function logApiCall(string $method, string $endpoint, int $status, array $response, ?string $nestedKey = null): void
    {

        if (! config('third-party-apis.log_calls')) {
            return;
        }

        $logData = [
            'provider' => $this->getProviderName(),
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'response_sample' => $this->getSampleResponse($response, $nestedKey),
        ];

        Log::channel('third-party-apis')->info('['.strtoupper($method).'] '.$endpoint.' **API Call**', $logData);

        if (config('third-party-apis.save_responses')) {
            $this->saveResponseSnapshot($endpoint, $response);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function getSampleResponse(array $response, ?string $nestedKey = null): array
    {
        if (! is_null($nestedKey)) {
            $response[$nestedKey] = array_slice($response, 0, 3);

            return $response;
        }

        return array_slice($response, 0, 3);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function saveResponseSnapshot(string $endpoint, array $response): void
    {
        $filename = sprintf(
            'api-responses/%s/%s_%s.json',
            $this->getProviderName(),
            str_replace('/', '_', $endpoint),
            now()->format('Y-m-d_H-i-s')
        );

        $jsonContent = json_encode($response, JSON_PRETTY_PRINT);
        if ($jsonContent !== false) {
            Storage::disk('local')->put($filename, $jsonContent);
        }
    }

    abstract public function getProviderName(): string;
}
