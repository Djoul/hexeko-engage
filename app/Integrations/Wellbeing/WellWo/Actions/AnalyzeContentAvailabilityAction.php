<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\DTOs\AnalysisResultDTO;
use App\Integrations\Wellbeing\WellWo\DTOs\ContentAvailabilityDTO;
use App\Integrations\Wellbeing\WellWo\Services\WellWoClassService;
use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyzeContentAvailabilityAction
{
    private const SUPPORTED_LANGUAGES = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

    public function __construct(
        private readonly WellWoClassService $classService,
        private readonly WellWoProgramService $programService,
        private readonly ContentAvailabilityStorage $storage
    ) {}

    /**
     * @param  array  $languages  Languages to analyze (empty = all)
     * @param  bool  $dryRun  Don't save results to S3
     * @param  bool  $force  Force analysis even if recent data exists
     * @return AnalysisResultDTO[]
     */
    public function execute(array $languages = [], bool $dryRun = false, bool $force = false): array
    {
        $languages = $languages === [] ? self::SUPPORTED_LANGUAGES : $languages;
        $results = [];

        foreach ($languages as $language) {
            $results[] = $this->analyzeLanguage($language, $dryRun);
        }

        return $results;
    }

    private function analyzeLanguage(string $language, bool $dryRun): AnalysisResultDTO
    {
        $result = new AnalysisResultDTO;
        $result->language = $language;
        $startTime = microtime(true);

        try {
            Log::info("Starting WellWo content analysis for language: {$language}");

            // Collect all content IDs from the 4 endpoints
            $contentIds = $this->collectContentIds($language);
            // Build availability DTO
            $availabilityDto = new ContentAvailabilityDTO;
            $availabilityDto->version = '1.0.0';
            $availabilityDto->analyzedAt = now()->toIso8601String();
            $availabilityDto->language = $language;
            $availabilityDto->endpoints = $contentIds;

            // Calculate statistics
            $totalItems = 0;
            $availableItems = 0;
            foreach ($contentIds as $ids) {
                $count = count($ids);
                $totalItems += $count;
                $availableItems += $count;
            }

            $availabilityDto->statistics = [
                'totalItems' => $totalItems,
                'availableItems' => $availableItems,
                'analysisTime' => microtime(true) - $startTime,
            ];

            // Save to S3 unless dry run
            if (! $dryRun) {
                $this->storage->saveAvailability($language, $availabilityDto);
                Log::info("Saved availability data for language: {$language}");
            } else {
                Log::info("[DRY RUN] Would save availability data for language: {$language}");
            }

            // Update result
            $result->success = true;
            $result->itemsAnalyzed = $totalItems;
            $result->itemsAvailable = $availableItems;

        } catch (Exception $e) {
            Log::error("Failed to analyze content for language {$language}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $result->success = false;
            $result->error = $e->getMessage();
        }

        $result->duration = microtime(true) - $startTime;

        return $result;
    }

    private function collectContentIds(string $language): array
    {
        $endpoints = [];

        // 1. Get class disciplines
        try {
            $classes = $this->classService->getClasses($language, true);
            $endpoints['recordedClassesGetDisciplines'] = $this->extractIds($classes);
            Log::debug("Found {count} classes for {$language}", ['count' => count($endpoints['recordedClassesGetDisciplines'])]);
        } catch (Exception $e) {
            Log::warning("Failed to get classes for {$language}: {$e->getMessage()}");
            $endpoints['recordedClassesGetDisciplines'] = [];
        }

        // 2. Get class videos for each class and track which classes have videos
        $classesWithVideos = [];
        $classVideoIds = [];
        try {
            foreach ($endpoints['recordedClassesGetDisciplines'] as $classId) {
                try {
                    $videos = $this->classService->getClasseVideoById($classId, $language);
                    if ($videos !== null && count($videos) > 0) {
                        // Mark this class as having videos
                        $classesWithVideos[] = $classId;

                        foreach ($videos as $video) {
                            if (isset($video->name)) {
                                // Store video with class association
                                $videoId = md5($classId.'_'.$video->name);
                                $classVideoIds[] = $videoId;
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::debug("Failed to get videos for class {$classId}: {$e->getMessage()}");
                }
            }

            // Only keep classes that have videos
            $endpoints['recordedClassesGetDisciplines'] = $classesWithVideos;
            $endpoints['recordedClassesGetVideoList'] = $classVideoIds;

            Log::debug("Found {classCount} classes with {videoCount} videos for {$language}", [
                'classCount' => count($classesWithVideos),
                'videoCount' => count($classVideoIds),
            ]);
        } catch (Exception $e) {
            Log::warning("Failed to get class videos for {$language}: {$e->getMessage()}");
            $endpoints['recordedClassesGetDisciplines'] = [];
            $endpoints['recordedClassesGetVideoList'] = [];
        }

        // 3. Get programs
        try {
            $programs = $this->programService->getPrograms($language, true);
            $endpoints['recordedProgramsGetPrograms'] = $this->extractIds($programs);
            Log::debug("Found {count} programs for {$language}", ['count' => count($endpoints['recordedProgramsGetPrograms'])]);
        } catch (Exception $e) {
            Log::warning("Failed to get programs for {$language}: {$e->getMessage()}");
            $endpoints['recordedProgramsGetPrograms'] = [];
        }

        // 4. Get program videos for each program and track which programs have videos
        $programsWithVideos = [];
        $programVideoIds = [];
        try {
            foreach ($endpoints['recordedProgramsGetPrograms'] as $programId) {
                try {
                    $videos = $this->programService->getProgramVideoById($programId, $language);
                    if ($videos !== null && count($videos) > 0) {
                        // Mark this program as having videos
                        $programsWithVideos[] = $programId;

                        foreach ($videos as $video) {
                            if (isset($video->id)) {
                                $programVideoIds[] = $video->id;
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::debug("Failed to get videos for program {$programId}: {$e->getMessage()}");
                }
            }

            // Only keep programs that have videos
            $endpoints['recordedProgramsGetPrograms'] = $programsWithVideos;
            $endpoints['recordedProgramsGetVideoList'] = $programVideoIds;

            Log::debug("Found {programCount} programs with {videoCount} videos for {$language}", [
                'programCount' => count($programsWithVideos),
                'videoCount' => count($programVideoIds),
            ]);
        } catch (Exception $e) {
            Log::warning("Failed to get program videos for {$language}: {$e->getMessage()}");
            $endpoints['recordedProgramsGetPrograms'] = [];
            $endpoints['recordedProgramsGetVideoList'] = [];
        }

        return $endpoints;
    }

    private function extractIds(Collection $items): array
    {
        // Handle both flat collections and nested structures
        if ($items->isEmpty()) {
            return [];
        }

        // Check if this is a nested structure with mediaItems
        if ($items->has('mediaItems')) {
            return collect($items->get('mediaItems'))
                ->pluck('id')
                ->filter()
                ->values()
                ->toArray();
        }

        // Flat collection of items
        return $items
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
    }
}
