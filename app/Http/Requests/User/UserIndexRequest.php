<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $data = [];

        // Transform financer_id from string to array if it contains comma-separated UUIDs
        if ($this->has('financer_id')) {
            $financerId = $this->input('financer_id');
            if (is_string($financerId) && str_contains($financerId, ',')) {
                $data['financer_id'] = array_map('trim', explode(',', $financerId));
            } elseif (is_string($financerId)) {
                $data['financer_id'] = [trim($financerId)];
            }
        }

        // Transform division_id from string to array if it contains comma-separated UUIDs
        if ($this->has('division_id')) {
            $divisionId = $this->input('division_id');
            if (is_string($divisionId) && str_contains($divisionId, ',')) {
                $data['division_id'] = array_map('trim', explode(',', $divisionId));
            } elseif (is_string($divisionId)) {
                $data['division_id'] = [trim($divisionId)];
            }
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $rules = [];

        // Validate financer_id if present
        if ($this->has('financer_id')) {
            $rules['financer_id'] = ['array'];
            $rules['financer_id.*'] = ['uuid', 'exists:financers,id'];
        }

        // Validate division_id if present
        if ($this->has('division_id')) {
            $rules['division_id'] = ['array'];
            $rules['division_id.*'] = ['uuid', 'exists:divisions,id'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }
}
