<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that an email is unique per financer for active users.
 *
 * An email can exist multiple times in the system, but must be unique
 * for each financer when the user is active (financer_user.active = true).
 *
 * Inactive users can have duplicate emails within the same financer.
 */
class UniqueEmailPerActiveFinancer implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $financerId  The financer ID to check against
     * @param  string|null  $ignoreUserId  User ID to ignore (for updates)
     */
    public function __construct(
        protected string $financerId,
        protected ?string $ignoreUserId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Skip validation if financerId is empty or invalid
        // The 'required|uuid|exists' rules on financer_id will handle that validation
        if (empty($this->financerId) || ! $this->isValidUuid($this->financerId)) {
            return;
        }

        // Check if email exists for this financer with active status
        $query = User::where('email', $value)
            ->whereHas('financers', function ($query): void {
                $query->where('financer_user.financer_id', $this->financerId)
                    ->where('financer_user.active', true);
            });

        // Ignore specific user (for updates)
        if ($this->ignoreUserId) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('Email already exists for this financer');
        }
    }

    /**
     * Check if a string is a valid UUID (any version).
     */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }
}
