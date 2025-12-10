<?php

namespace App\Http\Requests\Push;

use App\Enums\DeviceTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'subscription_id' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', Rule::in(DeviceTypes::getValues())],
            'device_model' => ['nullable', 'string', 'max:100'],
            'device_os' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'device_type.in' => 'The device type must be one of: '.implode(', ', DeviceTypes::getValues()),
            'language.regex' => 'The language must be in format: en-US, fr-FR, etc.',
            'timezone.timezone' => 'The timezone must be a valid timezone identifier.',
        ];
    }
}
