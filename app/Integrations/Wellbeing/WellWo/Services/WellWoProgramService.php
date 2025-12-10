<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Wellbeing\WellWo\DTOs\VideoDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoApiException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WellWoProgramService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    public function __construct(
        private readonly WellWoApiService $apiService
    ) {}

    public function getPrograms(string $lang = 'es', bool $forceRefresh = false): Collection
    {
        // DISABLED CACHE - Direct API call
        try {
            $response = $this->apiService->post([
                'lang' => $lang,
                'op' => 'healthyProgramsGetList',
            ]);

            return $this->transformProgramsResponse($response);
        } catch (WellWoApiException $e) {
            Log::warning('Failed to fetch WellWo programs', [
                'lang' => $lang,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @return array<int, VideoDTO>|null
     */
    public function getProgramVideoById(string $id, string $lang = 'es'): ?array
    {
        // DISABLED CACHE - Direct API call
        try {
            $response = $this->apiService->post(['lang' => $lang, 'id' => $id, 'op' => 'healthyProgramsGetVideoList']);

            if ($response['status'] !== 'OK') {
                return null;
            }
            unset($response['status']);
            $videoList = [];
            foreach ($response as $program) {
                if (is_array($program)) {
                    $videoList[] = VideoDTO::fromApiResponse($program);
                }
            }

            return $videoList;
        } catch (WellWoApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            Log::warning('Failed to fetch WellWo program details', [
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
    private function transformProgramsResponse(array $response): Collection
    {

        $programs = collect();

        foreach ($response as $key => $program) {
            // Skip the status key
            if ($key === 'status') {
                continue;
            }

            if (is_array($program) && array_key_exists('id', $program)) {
                $programs->push(WellWoDTO::fromApiResponse($program));
            }
        }

        return $programs;
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
                'op' => 'healthyProgramsGetList',
                'lang' => 'es',
            ]);

            return array_key_exists('status', $response) && $response['status'] === 'OK';
        } catch (Exception $e) {
            return false;
        }
    }
}
