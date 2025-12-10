<?php

namespace App\Repositories\Models;

use App\Models\User;
use Exception;

class UserSettingsRepository
{
    /**
     * Update user settings
     *
     * @throws Exception
     */
    public function updateUserSettings(User $user): User
    {
        try {
            $user->update([
                'locale' => $user->locale,
            ]);

            $freshUser = $user->fresh();

            if ($freshUser === null) {
                throw new Exception('Failed to refresh user after update');
            }

            return $freshUser;
        } catch (Exception $e) {
            throw new Exception('Failed to update user settings: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
