<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Wellbeing\WellWo\DTOs\ClassVideoDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WellWoClassService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    public function __construct(
        private readonly WellWoApiService $apiService
    ) {}

    public function getClasses(string $lang = 'es', bool $forceRefresh = false): Collection
    {
        // DISABLED CACHE - Direct API call
        try {
            $response = $this->apiService->post([
                'lang' => $lang,
                'op' => 'recordedClassesGetDisciplines',
            ]);

            return $this->transformClassesResponse($response);
        } catch (WellWoApiException $e) {
            Log::warning('Failed to fetch WellWo classes', [
                'lang' => $lang,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @return array<ClassVideoDTO>|null
     */
    public function getClasseVideoById(string $id, string $lang = 'es'): ?array
    {
        // DISABLED CACHE - Direct API call
        try {
            $response = $this->apiService->post([
                'lang' => $lang,
                'op' => 'recordedClassesGetVideoList',
                'id' => $id,
            ]);

            unset($response['status']);

            $videoList = [];

            // Check if mediaItems key exists and is an array
            if (array_key_exists('mediaItems', $response) && is_array($response['mediaItems'])) {
                foreach ($response['mediaItems'] as $video) {
                    if (is_array($video)) {
                        $videoList[] = ClassVideoDTO::fromApiResponse($video);
                    }
                }
            }

            return $videoList;
        } catch (WellWoApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            Log::warning('Failed to fetch WellWo video details', [
                'id' => $id,
                'lang' => $lang,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function transformClassesResponse(array $response): Collection
    {

        $classes = collect();

        foreach ($response as $key => $class) {
            // Skip the status key
            if ($key === 'status') {
                continue;
            }

            if (is_array($class) && array_key_exists('id', $class)) {
                $classes->push(WellWoDTO::fromApiResponse($class));
            }
        }

        return $classes;
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
            // Try to fetch videos to test connectivity
            $response = $this->apiService->post([
                'op' => 'healthyProgramsGetVideoList',
                'lang' => 'es',
                'id' => 'test',
            ]);

            return array_key_exists('status', $response) && $response['status'] === 'OK';
        } catch (Exception $e) {
            return false;
        }
    }
}
