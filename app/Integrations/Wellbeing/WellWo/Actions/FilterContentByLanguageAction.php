<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Storage\ContentAvailabilityStorage;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FilterContentByLanguageAction
{
    public function __construct(
        private readonly ContentAvailabilityStorage $storage,
        private readonly Config $config
    ) {}

    public function execute(Collection $content, string $language, string $endpoint): Collection
    {
        // Check if feature is enabled
        $isEnabled = $this->config->get('services.wellwo.filter_by_language', false);

        if (! $isEnabled) {
            return $content;
        }

        // Load availability data from storage
        $availability = $this->loadAvailability($language, $endpoint);

        if ($availability === null) {
            Log::warning("[{$endpoint}] No availability data for language {$language}, returning all content");

            return $content;
        }

        // Get available IDs for this endpoint
        $availableIds = $availability->getAvailableIds($endpoint);

        if (empty($availableIds)) {
            // No items available for this endpoint
            return collect();
        }
        // Note: Since AnalyzeContentAvailabilityAction now only stores categories with videos,
        // we don't need additional filtering for categories - the availableIds already represent
        // categories that have videos
        // Filter content to only include available IDs
        $content->count();

        return $content->filter(function ($item) use ($availableIds): bool {
            // Handle both arrays and objects (WellWoDTO, ClassVideoDTO, etc.)
            if (is_array($item)) {
                $id = $item['id'] ?? null;
            } else {
                // For DTO objects, check if they have an 'id' property, otherwise use name for ClassVideoDTO
                $id = property_exists($item, 'id') ? $item->id :
                     (property_exists($item, 'name') ? $item->name : null);
            }

            return in_array($id, $availableIds, true);
        })->values();
    }

    private function loadAvailability(string $language, string $endpoint): ?object
    {
        try {
            // Load from storage directly
            return $this->storage->loadAvailability($language);

        } catch (Exception $e) {
            Log::error("[{$endpoint}] Failed to load availability data for {$language}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
