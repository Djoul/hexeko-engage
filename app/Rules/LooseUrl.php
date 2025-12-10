<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class LooseUrl implements Rule
{
    public function passes($attribute, $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (! is_scalar($value)) {
            return false;
        }

        $valueString = (string) $value;

        $valueWithScheme = preg_match('#^https?://#', $valueString) ? $valueString : 'http://'.$valueString;

        return filter_var($valueWithScheme, FILTER_VALIDATE_URL) !== false;
    }

    public function message(): string
    {
        return 'The :attribute field must be a valid URL.';
    }
}
