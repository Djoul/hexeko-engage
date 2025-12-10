<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\Languages;
use App\Models\FinancerUser;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateUserLanguageAction
{
    /**
     * Update user language for current financer context.
     *
     * @param  User  $user  The user to update
     * @param  string  $language  The language code from Languages enum
     *
     * @throws InvalidArgumentException If language is invalid
     */
    public function execute(User $user, string $language): void
    {
        // Validate language value
        if (! Languages::hasValue($language)) {
            throw new InvalidArgumentException("Invalid language: {$language}");
        }

        DB::transaction(function () use ($user, $language): void {
            // Get current financer ID from context
            $financerId = Context::get('financer_id');

            // Validate financer_id is a valid UUID if present
            if ($financerId && ! Str::isUuid($financerId)) {
                throw new InvalidArgumentException("Invalid financer_id UUID: {$financerId}");
            }

            if ($financerId) {
                // Update language for the specific financer if active (explicit casts for UUIDs)
                $pivot = FinancerUser::where('user_id', (string) $user->id)
                    ->where('financer_id', $financerId)
                    ->where('active', true)
                    ->first();

                if ($pivot) {
                    $previousLanguage = $pivot->language;
                    $pivot->language = $language;
                    $pivot->save();

                    Log::info('Updated financer language preference', [
                        'user_id' => $user->id,
                        'financer_id' => $financerId,
                        'previous_language' => $previousLanguage,
                        'new_language' => $language,
                    ]);
                }
            }

            // Always update user.locale for backward compatibility
            $user->locale = $language;
            $user->save();

            Log::info('Updated user locale', [
                'user_id' => $user->id,
                'previous_locale' => $user->getOriginal('locale'),
                'new_locale' => $language,
                'has_context' => $financerId !== null,
            ]);
        });
    }
}
