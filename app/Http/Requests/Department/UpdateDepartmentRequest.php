<?php

namespace App\Http\Requests\Department;

use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid|exists:financers,id',
            'parent_id' => [
                'nullable',
                'uuid',
                'exists:departments,id',
                new BelongsToCurrentFinancer('departments'),
                // The parent cannot have the same id as the current department being updated
                function ($attribute, $value, $fail): void {
                    // Only run this rule if we have a relevant department context (route binding)
                    $department = $this->route('department');
                    if ($department && $value !== null && $value == $department->id) {
                        $fail(__('The parent department cannot be the same as the current department.'));
                    }
                },
            ],
            'name' => 'required|string|max:255',
        ];
    }
}
