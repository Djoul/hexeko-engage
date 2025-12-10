<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @group Core/Financers
 *
 * Data validation for toggling financer active status
 */
class ToggleFinancerActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * The active status to set for the financer.
             * If not provided, the current status will be toggled.
             *
             * @var bool
             *
             * @example true
             */
            'active' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
