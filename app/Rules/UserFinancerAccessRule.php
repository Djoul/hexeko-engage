<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserFinancerAccessRule implements ValidationRule
{
    /**
     * The custom error message for this rule.
     */
    protected string $message;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $message = 'You can only access resources for your assigned financers.')
    {
        $this->message = $message;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['god', 'super_admin'])) {
            $userFinancerIds = $user->financers()->pluck('financers.id')->toArray();

            if (! in_array($value, $userFinancerIds)) {
                $fail($this->message);
            }
        }
    }
}
