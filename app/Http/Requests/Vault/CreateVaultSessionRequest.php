<?php

namespace App\Http\Requests\Vault;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CreateVaultSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'financer_id' => [
                'required',
                'uuid',
                'exists:financers,id',
            ],
            'redirect_uri' => [
                'required',
                'url',
                'regex:/^https:\/\/.*/',
            ],
            'settings' => ['sometimes', 'array'],
            'settings.unified_apis' => [
                'sometimes',
                'array',
                /** @param \Closure(string): never $fail */
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && ! in_array('hris', $value, true)) {
                        $fail('The unified_apis must contain "hris".');
                    }
                },
            ],
            'settings.isolation_mode' => ['sometimes', 'boolean'],
            'settings.hide_resource_settings' => ['sometimes', 'boolean'],
            'settings.sandbox_mode' => ['sometimes', 'boolean'],
            'settings.session_length' => ['sometimes', 'string'],
            'settings.show_logs' => ['sometimes', 'boolean'],
            'settings.show_suggestions' => ['sometimes', 'boolean'],
            'settings.show_sidebar' => ['sometimes', 'boolean'],
            'settings.auto_redirect' => ['sometimes', 'boolean'],
            'settings.hide_guides' => ['sometimes', 'boolean'],
            'settings.allow_actions' => ['sometimes', 'array'],
            'settings.custom_consumer_settings' => ['sometimes', 'array'],
            'settings.service_id' => [
                'sometimes',
                'string',
                'in:bamboohr,personio,workday,hibob,namely,sage-hr,adp-workforce-now,factorialhr,officient-io,breathehr,cascade-hr,freshteam,folks-hr,gusto,lucca-hr,loket-nl,microsoft-dynamics-hr,nmbrs,payfit,paylocity,rippling,sdworx,sympa,zenefits,google-workspace',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'financer_id.required' => 'The financer ID is required.',
            'financer_id.uuid' => 'The financer ID must be a valid UUID.',
            'financer_id.exists' => 'The selected financer does not exist.',
            'redirect_uri.required' => 'The redirect URI is required.',
            'redirect_uri.url' => 'The redirect URI must be a valid URL.',
            'redirect_uri.regex' => 'The redirect URI must use HTTPS.',
            'settings.unified_apis.required' => 'The unified APIs setting is required when settings are provided.',
            'settings.service_id.string' => 'The SIRH service provider must be a string.',
            'settings.service_id.in' => 'The service ID must be one of the supported SIRH providers: bamboohr, personio, workday, hibob, namely, sage-hr, adp-workforce-now, factorialhr, officient-io, and others.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'financer_id' => 'financer ID',
            'redirect_uri' => 'redirect URI',
            'settings.unified_apis' => 'unified APIs',
            'settings.isolation_mode' => 'isolation mode',
            'settings.hide_resource_settings' => 'hide resource settings',
            'settings.sandbox_mode' => 'sandbox mode',
            'settings.session_length' => 'session length',
            'settings.show_logs' => 'show logs',
            'settings.show_suggestions' => 'show suggestions',
            'settings.show_sidebar' => 'show sidebar',
            'settings.auto_redirect' => 'auto redirect',
            'settings.hide_guides' => 'hide guides',
            'settings.allow_actions' => 'allow actions',
            'settings.custom_consumer_settings' => 'custom consumer settings',
            'settings.service_id' => 'SIRH service provider',
        ];
    }
}
