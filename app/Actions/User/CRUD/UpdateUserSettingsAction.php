<?php

declare(strict_types=1);

namespace App\Actions\User\CRUD;

use App\Actions\User\UpdateUserLanguageAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Action to update user settings
 *
 * Handles updating user settings with special handling for locale changes.
 * Locale updates are processed through UpdateUserLanguageAction to ensure
 * financer context is properly handled.
 */
class UpdateUserSettingsAction
{
    public function __construct(
        private readonly UpdateUserLanguageAction $updateUserLanguageAction
    ) {}

    /**
     * Execute the update user settings action
     *
     * @param  User  $user  User to update
     * @param  array<string, mixed>  $payload  Settings to update
     * @return User Updated user model
     */
    public function execute(User $user, array $payload): User
    {
        return DB::transaction(function () use ($user, $payload): User {
            // Handle locale update separately via UpdateUserLanguageAction
            // This ensures proper financer context handling
            if (array_key_exists('locale', $payload)) {
                if ($payload['locale'] !== null) {
                    $locale = $payload['locale'];
                    if (is_string($locale)) {
                        $this->updateUserLanguageAction->execute($user, $locale);
                    }
                }
                // Always remove locale from payload to avoid issues (null constraint or double update)
                unset($payload['locale']);
            }

            // Update other settings if any remain
            if ($payload !== []) {
                $user->update($payload);
            }

            // Return fresh user instance
            $freshUser = $user->fresh();

            return $freshUser !== null ? $freshUser : $user;
        });
    }
}
