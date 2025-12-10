<?php

namespace App\Services\Models;

use App\Models\User;
use App\Repositories\Models\UserSettingsRepository;

class UserSettingsService
{
    public function __construct(
        private readonly UserSettingsRepository $userRepository,
        private readonly UserService $userService,
    ) {}

    /**
     * Change user settings
     *
     * @param  User  $user  The user to update settings for
     * @param  array<string, mixed>  $payload  The settings payload
     * @return User The updated user
     */
    public function changeUserSettings(User $user, array $payload): User
    {
        // Use UserService for language update to handle financer context
        if (array_key_exists('locale', $payload) && $payload['locale'] !== null) {
            $locale = $payload['locale'];
            if (is_string($locale)) {
                $this->userService->updateUserLanguage($user, $locale);
            }
            // Remove locale from payload to avoid double update
            unset($payload['locale']);
        }

        // Update other settings if any remain
        if ($payload !== []) {
            return $this->userRepository->updateUserSettings($user);
        }

        return $user->fresh();
    }
}
