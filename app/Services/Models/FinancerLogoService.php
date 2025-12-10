<?php

namespace App\Services\Models;

use App\Models\Financer;
use App\Services\Media\HeicImageConversionService;
use Exception;
use Illuminate\Support\Facades\Log;

class FinancerLogoService
{
    public function __construct(
        private readonly HeicImageConversionService $heicConversionService
    ) {}

    public function updateLogo(Financer $financer, string $logo): void
    {
        Log::info("Updating logo for financer {$financer->id}");

        try {
            // Convert HEIC to JPG if needed
            $processedImage = $this->heicConversionService->processImage($logo);

            $financer->addMediaFromBase64($processedImage)->toMediaCollection('logo');

            refreshModelCache($financer);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function removeLogo(Financer $financer): void
    {
        Log::info("Removing logo for financer {$financer->id}");

        $financer->clearMediaCollection('logo');

        refreshModelCache($financer);
    }
}
