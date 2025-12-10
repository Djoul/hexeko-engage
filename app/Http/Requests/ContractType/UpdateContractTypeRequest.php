<?php

namespace App\Http\Requests\ContractType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid',
            'name' => 'required|string|max:255',
        ];
    }
}
