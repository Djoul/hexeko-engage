<?php

namespace App\Services\Models;

use App\Models\User;
use App\Services\Media\HeicImageConversionService;
use Exception;
use Log;

class UserProfileImageService
{
    public function __construct(
        private readonly HeicImageConversionService $heicConversionService
    ) {}

    public function updateProfileImage(User $user, string $image): void
    {
        Log::info('Updating profile image for user '.$user->id, ['image' => $image]);
        try {
            // Convert HEIC to JPG if needed
            $processedImage = $this->heicConversionService->processImage($image);

            $user->addMediaFromBase64($processedImage)->toMediaCollection('profile_image');
            refreshModelCache($user);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
