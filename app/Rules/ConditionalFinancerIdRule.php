<?php

namespace App\Rules;

use App\Enums\IDP\PermissionDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class ConditionalFinancerIdRule implements ValidationRule
{
    /**
     * The custom error message for this rule.
     */
    protected string $message;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $message = 'The financer_id field is required when you do not have permission to manage any financer.')
    {
        $this->message = $message;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if (! $user) {
            $fail('User must be authenticated.');

            return;
        }

        // Check if user has permission to manage any financer
        $canManageAnyFinancer = $user->hasPermissionTo(PermissionDefaults::MANAGE_ANY_FINANCER);

        // If user has permission to manage any financer, no additional validation needed
        if ($canManageAnyFinancer) {
            return;
        }

        // If user doesn't have permission and financer_id is provided, validate access
        if (! empty($value)) {
            $userFinancerIds = $user->financers()->pluck('financers.id')->toArray();

            if (! in_array($value, $userFinancerIds)) {
                $fail('You can only access resources for your assigned financers.');
            }
        }
    }
}
